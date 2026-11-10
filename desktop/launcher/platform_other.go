//go:build !windows

package main

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
)

// نسخة للاختبار على لينكس فقط
func phpBinary(p *paths) string {
	if b := os.Getenv("LAUNCHER_PHP"); b != "" {
		return b
	}
	return filepath.Join(p.php, "php")
}
func iniArgs(p *paths) []string { return nil } // لينكس: إعدادات PHP الخاصة بالنظام

func hideWindow(cmd *exec.Cmd)  {}
func initJob()                  {}
func attachToJob(cmd *exec.Cmd) {}
func showError(msg string)      { fmt.Fprintln(os.Stderr, msg) }
func openWindow(p *paths, url string) <-chan struct{} {
	fmt.Println("OPEN", url)
	return nil
}
