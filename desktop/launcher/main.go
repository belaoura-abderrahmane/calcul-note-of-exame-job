// برنامج تشغيل سطح المكتب: ملف exe واحد يحتوي على PHP والبرنامج.
// عند التشغيل: يفك الملفات (أول مرة فقط) ← يجهز قاعدة البيانات ← يشغل خادم PHP
// ← يفتح نافذة التطبيق ← يغلق كل شيء عند إغلاق النافذة.
package main

import (
	"archive/zip"
	"bytes"
	"crypto/rand"
	"crypto/sha256"
	_ "embed"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net"
	"net/http"
	"net/http/httputil"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"sync"
	"sync/atomic"
	"time"
)

const appName = "CalculNote"

//go:embed payload.zip
var payload []byte

// ---------------------------------------------------------------- الحالة

type state struct {
	mu       sync.Mutex
	stage    string
	progress int
	ready    bool
	err      string
}

var st = &state{stage: "جاري التحضير..."}

func setStage(s string, p int) {
	st.mu.Lock()
	st.stage, st.progress = s, p
	st.mu.Unlock()
	log.Println("stage:", s, p)
}

func fail(err error) {
	st.mu.Lock()
	st.err = err.Error()
	st.mu.Unlock()
	log.Println("ERROR:", err)
}

var lastPing atomic.Int64

// ---------------------------------------------------------------- المسارات

type paths struct {
	base    string // %LOCALAPPDATA%\CalculNote
	runtime string // base\rt-<hash>
	php     string
	app     string
	data    string // قاعدة البيانات
	logFile string
}

func resolvePaths() (*paths, error) {
	base, err := os.UserCacheDir() // %LOCALAPPDATA% على ويندوز
	if err != nil {
		return nil, err
	}
	base = filepath.Join(base, appName)
	sum := sha256.Sum256(payload)
	rt := filepath.Join(base, "rt-"+hex.EncodeToString(sum[:])[:12])

	p := &paths{base: base, runtime: rt, php: filepath.Join(rt, "php"), app: filepath.Join(rt, "app")}

	// البيانات بجانب ملف exe (لنقلها مع البرنامج) إن أمكن الكتابة هناك
	exe, _ := os.Executable()
	candidate := filepath.Join(filepath.Dir(exe), appName+"-Data")
	if os.MkdirAll(candidate, 0o755) == nil && writable(candidate) {
		p.data = candidate
	} else {
		p.data = filepath.Join(base, "data")
	}
	if err := os.MkdirAll(p.data, 0o755); err != nil {
		return nil, err
	}
	if err := os.MkdirAll(base, 0o755); err != nil {
		return nil, err
	}
	p.logFile = filepath.Join(base, "launcher.log")
	return p, nil
}

func writable(dir string) bool {
	f, err := os.CreateTemp(dir, ".w*")
	if err != nil {
		return false
	}
	name := f.Name()
	f.Close()
	os.Remove(name)
	return true
}

// ---------------------------------------------------------------- فك الملفات

func extract(p *paths) error {
	marker := filepath.Join(p.runtime, ".complete")
	if _, err := os.Stat(marker); err == nil {
		return nil
	}
	setStage("فك ملفات البرنامج (مرة واحدة فقط)...", 0)
	os.RemoveAll(p.runtime)

	zr, err := zip.NewReader(bytes.NewReader(payload), int64(len(payload)))
	if err != nil {
		return fmt.Errorf("ملف البرنامج تالف: %w", err)
	}
	total := len(zr.File)
	for i, f := range zr.File {
		target := filepath.Join(p.runtime, filepath.FromSlash(f.Name))
		if !strings.HasPrefix(target, filepath.Clean(p.runtime)+string(os.PathSeparator)) {
			continue
		}
		if f.FileInfo().IsDir() {
			os.MkdirAll(target, 0o755)
			continue
		}
		if err := os.MkdirAll(filepath.Dir(target), 0o755); err != nil {
			return err
		}
		rc, err := f.Open()
		if err != nil {
			return err
		}
		out, err := os.OpenFile(target, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, f.Mode()|0o600)
		if err != nil {
			rc.Close()
			return err
		}
		_, err = io.Copy(out, rc)
		rc.Close()
		out.Close()
		if err != nil {
			return err
		}
		if i%200 == 0 {
			setStage("فك ملفات البرنامج (مرة واحدة فقط)...", i*100/total)
		}
	}
	// حذف النسخ القديمة
	if entries, err := os.ReadDir(p.base); err == nil {
		for _, e := range entries {
			if e.IsDir() && strings.HasPrefix(e.Name(), "rt-") && filepath.Join(p.base, e.Name()) != p.runtime {
				os.RemoveAll(filepath.Join(p.base, e.Name()))
			}
		}
	}
	return os.WriteFile(marker, []byte(time.Now().String()), 0o644)
}

// ---------------------------------------------------------------- الإعداد

func appKey(p *paths) (string, error) {
	keyFile := filepath.Join(p.data, "app.key")
	if b, err := os.ReadFile(keyFile); err == nil && len(bytes.TrimSpace(b)) > 0 {
		return string(bytes.TrimSpace(b)), nil
	}
	buf := make([]byte, 32)
	if _, err := rand.Read(buf); err != nil {
		return "", err
	}
	key := "base64:" + base64.StdEncoding.EncodeToString(buf)
	return key, os.WriteFile(keyFile, []byte(key), 0o600)
}

func configure(p *paths) (dbIsNew bool, err error) {
	setStage("تجهيز الإعدادات...", 0)
	key, err := appKey(p)
	if err != nil {
		return false, err
	}
	db := filepath.Join(p.data, "database.sqlite")
	if _, err := os.Stat(db); errors.Is(err, os.ErrNotExist) {
		dbIsNew = true
		if err := os.WriteFile(db, nil, 0o644); err != nil {
			return false, err
		}
	}

	env := strings.Join([]string{
		`APP_NAME="Calcul Note Concours"`,
		"APP_ENV=production",
		"APP_KEY=" + key,
		"APP_DEBUG=false",
		"APP_URL=http://127.0.0.1",
		"APP_LOCALE=ar",
		"APP_FALLBACK_LOCALE=fr",
		"APP_TIMEZONE=Africa/Algiers",
		"DESKTOP_MODE=true",
		"LOG_CHANNEL=single",
		"LOG_LEVEL=warning",
		"DB_CONNECTION=sqlite",
		`DB_DATABASE="` + filepath.ToSlash(db) + `"`,
		"SESSION_DRIVER=file",
		"SESSION_LIFETIME=10080",
		"CACHE_STORE=file",
		"QUEUE_CONNECTION=sync",
		"BROADCAST_CONNECTION=log",
		"FILESYSTEM_DISK=local",
		"MAIL_MAILER=log",
		"",
	}, "\n")
	if err := os.WriteFile(filepath.Join(p.app, ".env"), []byte(env), 0o644); err != nil {
		return false, err
	}

	ini := strings.Join([]string{
		`extension_dir="` + filepath.Join(p.php, "ext") + `"`,
		"extension=intl",
		"extension=openssl",
		"extension=zip",
		"date.timezone=Africa/Algiers",
		"memory_limit=512M",
		"max_execution_time=120",
		"upload_max_filesize=20M",
		"post_max_size=25M",
		"variables_order=EGPCS",
		"display_errors=Off",
		"log_errors=On",
		`error_log="` + filepath.Join(p.base, "php-errors.log") + `"`,
		"opcache.enable=1",
		"opcache.enable_cli=1",
		"opcache.memory_consumption=128",
		"opcache.max_accelerated_files=20000",
		"realpath_cache_size=4096K",
		"realpath_cache_ttl=600",
		"",
	}, "\n")
	return dbIsNew, os.WriteFile(filepath.Join(p.php, "php.ini"), []byte(ini), 0o644)
}

func phpCmd(p *paths, args ...string) *exec.Cmd {
	all := append(iniArgs(p), args...)
	cmd := exec.Command(phpBinary(p), all...)
	cmd.Dir = p.app
	cmd.Env = append(os.Environ(), "PATH="+p.php+string(os.PathListSeparator)+os.Getenv("PATH"))
	hideWindow(cmd)
	return cmd
}

func artisan(p *paths, args ...string) error {
	cmd := phpCmd(p, append([]string{"artisan"}, args...)...)
	out, err := cmd.CombinedOutput()
	log.Printf("artisan %v:\n%s", args, out)
	if err != nil {
		return fmt.Errorf("فشل الأمر artisan %s: %v\n%s", strings.Join(args, " "), err, lastLines(string(out), 15))
	}
	return nil
}

func lastLines(s string, n int) string {
	lines := strings.Split(strings.TrimSpace(s), "\n")
	if len(lines) > n {
		lines = lines[len(lines)-n:]
	}
	return strings.Join(lines, "\n")
}

func freePort() (int, error) {
	l, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		return 0, err
	}
	defer l.Close()
	return l.Addr().(*net.TCPAddr).Port, nil
}

// ---------------------------------------------------------------- الإقلاع

var phpProc *exec.Cmd
var phpURL atomic.Pointer[url.URL]

func boot(p *paths) {
	if err := extract(p); err != nil {
		fail(err)
		return
	}
	dbIsNew, err := configure(p)
	if err != nil {
		fail(err)
		return
	}

	setStage("تحديث قاعدة البيانات...", 0)
	os.RemoveAll(filepath.Join(p.app, "bootstrap", "cache", "config.php"))
	if err := artisan(p, "migrate", "--force"); err != nil {
		fail(err)
		return
	}
	if dbIsNew {
		setStage("إنشاء البيانات الأولية...", 0)
		if err := artisan(p, "db:seed", "--force"); err != nil {
			fail(err)
			return
		}
	}

	setStage("تشغيل الخادم...", 0)
	port, err := freePort()
	if err != nil {
		fail(err)
		return
	}
	router := filepath.Join(p.app, "vendor", "laravel", "framework", "src", "Illuminate", "Foundation", "resources", "server.php")
	cmd := phpCmd(p, "-S", fmt.Sprintf("127.0.0.1:%d", port), router)
	cmd.Dir = filepath.Join(p.app, "public")
	logf, _ := os.OpenFile(filepath.Join(p.base, "php-server.log"), os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0o644)
	cmd.Stdout, cmd.Stderr = logf, logf
	if err := cmd.Start(); err != nil {
		fail(fmt.Errorf("تعذر تشغيل PHP: %w", err))
		return
	}
	attachToJob(cmd)
	phpProc = cmd

	target, _ := url.Parse(fmt.Sprintf("http://127.0.0.1:%d", port))
	deadline := time.Now().Add(60 * time.Second)
	for time.Now().Before(deadline) {
		resp, err := http.Get(target.String() + "/up")
		if err == nil {
			resp.Body.Close()
			if resp.StatusCode < 500 {
				phpURL.Store(target)
				st.mu.Lock()
				st.ready = true
				st.mu.Unlock()
				log.Println("ready on", target)
				return
			}
		}
		time.Sleep(300 * time.Millisecond)
	}
	fail(errors.New("لم يستجب خادم PHP خلال 60 ثانية. راجع الملف php-server.log"))
}

// ---------------------------------------------------------------- الواجهة الأمامية

func frontHandler(p *paths) http.Handler {
	var proxy *httputil.ReverseProxy
	var once sync.Once

	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/__desktop/ping":
			lastPing.Store(time.Now().Unix())
			w.WriteHeader(http.StatusNoContent)
			return
		case "/__desktop/status":
			lastPing.Store(time.Now().Unix())
			st.mu.Lock()
			b, _ := json.Marshal(map[string]any{"ready": st.ready, "stage": st.stage, "progress": st.progress, "error": st.err, "log": p.logFile})
			st.mu.Unlock()
			w.Header().Set("Content-Type", "application/json")
			w.Header().Set("Cache-Control", "no-store")
			w.Write(b)
			return
		}

		target := phpURL.Load()
		if target == nil {
			lastPing.Store(time.Now().Unix())
			w.Header().Set("Content-Type", "text/html; charset=utf-8")
			w.Header().Set("Cache-Control", "no-store")
			w.Write([]byte(loadingPage))
			return
		}
		once.Do(func() {
			proxy = httputil.NewSingleHostReverseProxy(target)
			proxy.ErrorHandler = func(w http.ResponseWriter, r *http.Request, err error) {
				log.Println("proxy error:", err)
				http.Error(w, "خادم PHP لا يستجيب: "+err.Error(), http.StatusBadGateway)
			}
		})
		proxy.ServeHTTP(w, r)
	})
}

// ---------------------------------------------------------------- مثيل واحد

func lockFile(p *paths) string { return filepath.Join(p.base, "instance.port") }

func existingInstance(p *paths) string {
	b, err := os.ReadFile(lockFile(p))
	if err != nil {
		return ""
	}
	u := "http://127.0.0.1:" + strings.TrimSpace(string(b))
	c := http.Client{Timeout: 2 * time.Second}
	resp, err := c.Get(u + "/__desktop/status")
	if err != nil {
		return ""
	}
	resp.Body.Close()
	return u
}

// ---------------------------------------------------------------- main

func main() {
	p, err := resolvePaths()
	if err != nil {
		showError("تعذر تحديد مجلد البرنامج:\n" + err.Error())
		return
	}
	if f, err := os.OpenFile(p.logFile, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0o644); err == nil {
		log.SetOutput(f)
		defer f.Close()
	}
	log.Println("data dir:", p.data, "runtime:", p.runtime)

	// إن كان البرنامج يعمل مسبقاً: افتح نافذة جديدة فقط
	if u := existingInstance(p); u != "" {
		openWindow(p, u+"/admin")
		return
	}

	ln, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		showError("تعذر فتح منفذ محلي:\n" + err.Error())
		return
	}
	port := ln.Addr().(*net.TCPAddr).Port
	os.WriteFile(lockFile(p), []byte(fmt.Sprint(port)), 0o644)
	defer os.Remove(lockFile(p))

	initJob()
	go http.Serve(ln, frontHandler(p))
	go boot(p)

	appURL := fmt.Sprintf("http://127.0.0.1:%d/admin", port)
	started := time.Now()
	lastPing.Store(time.Now().Unix())
	browserDone := openWindow(p, appURL)

	// ننتظر إغلاق النافذة: خروج المتصفح، أو انقطاع النبض لمدة 3 دقائق
	for {
		select {
		case <-browserDone:
			if time.Since(started) > 15*time.Second {
				log.Println("browser window closed")
				shutdown()
				return
			}
			browserDone = nil // سلّم المتصفح العملية لنسخة أخرى: نعتمد على النبض
		case <-time.After(5 * time.Second):
			if time.Since(time.Unix(lastPing.Load(), 0)) > 3*time.Minute {
				log.Println("no heartbeat, exiting")
				shutdown()
				return
			}
		}
	}
}

func shutdown() {
	if phpProc != nil && phpProc.Process != nil {
		phpProc.Process.Kill()
	}
}

const loadingPage = `<!DOCTYPE html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>حساب نقاط المسابقات</title>
<style>
body{margin:0;height:100vh;display:flex;align-items:center;justify-content:center;font-family:"Segoe UI",Tahoma,sans-serif;background:linear-gradient(135deg,#ecfdf5,#f8fafc);color:#0f172a}
.card{background:#fff;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);padding:40px 48px;text-align:center;min-width:360px;max-width:640px}
h1{font-size:22px;margin:0 0 6px;color:#047857}.sub{color:#64748b;margin-bottom:26px}
.spin{width:46px;height:46px;border:5px solid #d1fae5;border-top-color:#059669;border-radius:50%;margin:0 auto 18px;animation:s 1s linear infinite}
@keyframes s{to{transform:rotate(360deg)}}
.bar{height:8px;background:#e2e8f0;border-radius:99px;overflow:hidden;margin-top:14px}.bar span{display:block;height:100%;width:0;background:#059669;transition:width .3s}
.err{display:none;text-align:right;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:10px;padding:14px;white-space:pre-wrap;font-size:13px;max-height:300px;overflow:auto}
</style></head><body><div class="card">
<h1>برنامج حساب نقاط مسابقات التوظيف</h1><div class="sub">على أساس الشهادة - الوظيف العمومي</div>
<div class="spin" id="spin"></div><div id="stage">جاري التحضير...</div><div class="bar" id="bar"><span id="p"></span></div>
<div class="err" id="err"></div></div>
<script>
async function tick(){
  try{
    const r=await fetch('/__desktop/status',{cache:'no-store'}); const s=await r.json();
    if(s.ready){location.replace('/admin');return}
    if(s.error){document.getElementById('spin').style.display='none';document.getElementById('bar').style.display='none';
      document.getElementById('stage').textContent='حدث خطأ أثناء التشغيل';const e=document.getElementById('err');e.style.display='block';
      e.textContent=s.error+'\n\nسجل التشغيل: '+s.log;return}
    document.getElementById('stage').textContent=s.stage;document.getElementById('p').style.width=(s.progress||0)+'%';
  }catch(e){}
  setTimeout(tick,500);
}
tick();
</script></body></html>`
