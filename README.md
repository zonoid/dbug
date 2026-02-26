# dBug – Modern Debug Dock for Joomla 5 & 6

**dBug** is a lightweight debugging plugin for Joomla that provides a modern, bottom-docked debug panel inspired by **Joomla Debug** and **browser developer tools**.

It replaces scattered `var_dump()` / `print_r()` output with a clean, structured, and non-intrusive debug UI.

---

## ✨ Features

- 🧰 **Bottom dock debug panel** (DevTools-style)
- 🧱 **Safe buffered rendering** (no broken layouts or DOM corruption)
- 📦 **Structured variable dumps** (arrays, objects, XML, scalars)
- 🧭 **Tabbed interface** (Dump / Info / Request)
- 📏 **Resizable & collapsible panel**
- 🌓 **Dark-mode friendly**
- ⌨️ **Keyboard support** (Esc to close)
- 🔢 **Automatic dump counter**
- 🧠 **Persistent state** (open/closed, height, active tab)
- ⚙️ **Joomla WebAssetManager integration**
- ♻️ **Backward compatible `dbug()` API**

---

## 🧠 Architecture Overview

dBug is intentionally split into three layers:

| Layer | Responsibility |
|-----|----------------|
| **Plugin** | Lifecycle, permissions, asset loading, final injection |
| **Renderer** | Formats variables into HTML (no direct output) |
| **Shim (`dbug()`)** | Public API & backward compatibility |

All debug output is **buffered** and injected **once** at the end of the page to ensure valid HTML and zero layout breakage.

---

## 🚀 Installation

1. Download the latest release from GitHub
2. Install via **Extensions → Manage → Install**
3. Enable **System – dBug**
4. (Optional) Enable **Joomla Debug Mode** if restricted by plugin settings

---

## 🧪 Usage

### Basic usage (recommended)

```php
dbug($variable);