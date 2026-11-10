//go:build windows

package main

import (
	"os"
	"os/exec"
	"path/filepath"
	"syscall"
	"unsafe"
)

var (
	kernel32                     = syscall.NewLazyDLL("kernel32.dll")
	user32                       = syscall.NewLazyDLL("user32.dll")
	procCreateJobObject          = kernel32.NewProc("CreateJobObjectW")
	procSetInformationJobObject  = kernel32.NewProc("SetInformationJobObject")
	procAssignProcessToJobObject = kernel32.NewProc("AssignProcessToJobObject")
	procOpenProcess              = kernel32.NewProc("OpenProcess")
	procMessageBox               = user32.NewProc("MessageBoxW")
	job                          uintptr
)

func phpBinary(p *paths) string { return filepath.Join(p.php, "php.exe") }

func iniArgs(p *paths) []string { return []string{"-c", filepath.Join(p.php, "php.ini")} }

func hideWindow(cmd *exec.Cmd) {
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true, CreationFlags: 0x08000000} // CREATE_NO_WINDOW
}

// كائن Job: يضمن إغلاق PHP تلقائياً حتى لو أُغلق البرنامج بالقوة
type jobBasicLimit struct {
	PerProcessUserTimeLimit int64
	PerJobUserTimeLimit     int64
	LimitFlags              uint32
	MinimumWorkingSetSize   uintptr
	MaximumWorkingSetSize   uintptr
	ActiveProcessLimit      uint32
	Affinity                uintptr
	PriorityClass           uint32
	SchedulingClass         uint32
}
type ioCounters struct{ a, b, c, d, e, f uint64 }
type jobExtendedLimit struct {
	Basic                 jobBasicLimit
	IoInfo                ioCounters
	ProcessMemoryLimit    uintptr
	JobMemoryLimit        uintptr
	PeakProcessMemoryUsed uintptr
	PeakJobMemoryUsed     uintptr
}

func initJob() {
	h, _, _ := procCreateJobObject.Call(0, 0)
	if h == 0 {
		return
	}
	var info jobExtendedLimit
	info.Basic.LimitFlags = 0x2000 // JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE
	procSetInformationJobObject.Call(h, 9, uintptr(unsafe.Pointer(&info)), unsafe.Sizeof(info))
	job = h
}

func attachToJob(cmd *exec.Cmd) {
	if job == 0 || cmd.Process == nil {
		return
	}
	const access = 0x0001 | 0x0100 // PROCESS_TERMINATE | PROCESS_SET_QUOTA
	h, _, _ := procOpenProcess.Call(access, 0, uintptr(cmd.Process.Pid))
	if h != 0 {
		procAssignProcessToJobObject.Call(job, h)
		syscall.CloseHandle(syscall.Handle(h))
	}
}

func showError(msg string) {
	t, _ := syscall.UTF16PtrFromString("حساب نقاط المسابقات")
	m, _ := syscall.UTF16PtrFromString(msg)
	procMessageBox.Call(0, uintptr(unsafe.Pointer(m)), uintptr(unsafe.Pointer(t)), 0x10|0x00100000) // MB_ICONERROR | MB_RTLREADING
}

func findBrowser() string {
	var cands []string
	for _, env := range []string{"ProgramFiles(x86)", "ProgramFiles", "LOCALAPPDATA"} {
		root := os.Getenv(env)
		if root == "" {
			continue
		}
		cands = append(cands,
			filepath.Join(root, "Microsoft", "Edge", "Application", "msedge.exe"),
			filepath.Join(root, "Google", "Chrome", "Application", "chrome.exe"),
			filepath.Join(root, "BraveSoftware", "Brave-Browser", "Application", "brave.exe"),
		)
	}
	for _, c := range cands {
		if _, err := os.Stat(c); err == nil {
			return c
		}
	}
	return ""
}

// يفتح نافذة تطبيق مستقلة (Edge/Chrome بوضع --app) ويرجع قناة تُغلق عند إغلاقها
func openWindow(p *paths, url string) <-chan struct{} {
	done := make(chan struct{})
	if b := findBrowser(); b != "" {
		cmd := exec.Command(b,
			"--app="+url,
			"--user-data-dir="+filepath.Join(p.base, "window"),
			"--no-first-run", "--no-default-browser-check",
			"--disable-features=Translate,BackForwardCache",
			"--window-size=1400,900",
			"--lang=ar",
		)
		if err := cmd.Start(); err == nil {
			go func() { cmd.Wait(); close(done) }()
			return done
		}
	}
	// احتياط: المتصفح الافتراضي (يُعتمد على النبض لمعرفة الإغلاق)
	exec.Command("rundll32", "url.dll,FileProtocolHandler", url).Start()
	return nil
}
