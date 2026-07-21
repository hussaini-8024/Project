# Ansible Modules — In-Depth Guide

## Table of Contents

1. [Introduction to Ansible](#introduction-to-ansible)
2. [What Is a Module?](#what-is-a-module)
3. [Why We Use Modules](#why-we-use-modules)
4. [Module vs Command/Shell](#module-vs-commandshell)
5. [Module Categories](#module-categories)
6. [Anatomy of a Module Task](#anatomy-of-a-module-task)
7. [Idempotency Explained](#idempotency-explained)
8. [Built-in vs Collection Modules](#built-in-vs-collection-modules)
9. [Real-World Examples (This Project)](#real-world-examples-this-project)
10. [Best Practices](#best-practices)
11. [Summary](#summary)

---

## Introduction to Ansible

**Ansible** is an open-source automation tool used to configure systems, deploy applications, and orchestrate IT workflows. It uses a simple YAML syntax called **playbooks** and connects to managed nodes over SSH (Linux) or WinRM (Windows) without requiring agents on target machines.

Key components:

| Component | Role |
|-----------|------|
| **Control Node** | Machine where Ansible is installed (e.g., AWX server) |
| **Managed Node** | Target server/PC being configured |
| **Inventory** | List of hosts and groups |
| **Playbook** | YAML file defining automation steps |
| **Module** | Reusable unit that performs a specific task |
| **Role** | Organized collection of tasks, variables, and templates |

---

## What Is a Module?

An **Ansible module** is a standalone script (or unit of code) that Ansible pushes to a managed node to perform a **specific, well-defined action**. Each module knows how to:

- Check the **current state** of a resource
- Compare it to the **desired state** you define
- Make changes **only when needed**
- Return structured **JSON output** (changed, failed, msg, etc.)

### Simple Definition

> A module is the **"verb"** in Ansible — it is the action you want to perform (install a package, copy a file, start a service, create a user).

### Example (Minimal)

```yaml
- name: Ensure staging directory exists
  ansible.windows.win_file:
    path: C:\Temp\Office2016
    state: directory
```

Here, `ansible.windows.win_file` is the **module**. It creates a directory if it does not exist.

### How Modules Execute

```
Control Node (AWX)          Managed Node (Windows PC)
      |                              |
      |  1. Push module + params     |
      |----------------------------->|
      |                              | 2. Module runs locally
      |                              | 3. Checks current state
      |                              | 4. Applies change (if needed)
      |  5. Return JSON result       |
      |<-----------------------------|
      |  6. Update playbook output   |
```

Modules are **agentless**: Ansible copies the module to the target, runs it, then removes temporary files.

---

## Why We Use Modules

### 1. Idempotency (Safe Re-runs)

Modules check state before acting. Running the same playbook twice does not break anything — if the directory already exists, `win_file` reports **ok** (no change) instead of failing or duplicating work.

### 2. Declarative Configuration

You describe **what** you want (`state: directory`), not **how** to achieve it step-by-step. The module handles OS-specific details.

### 3. Structured Output

Every module returns JSON with fields like:

```json
{
  "changed": true,
  "failed": false,
  "msg": "Directory created",
  "path": "C:\\Temp\\Office2016"
}
```

This enables conditionals (`when:`), registers (`register:`), and error handling.

### 4. Cross-Platform Abstraction

The same playbook pattern works across environments when you use the right module family:

- `ansible.builtin.file` → Linux files
- `ansible.windows.win_file` → Windows files

### 5. Security and Auditability

Modules use documented parameters instead of arbitrary shell commands, making playbooks easier to review, test, and approve in enterprise workflows (e.g., AWX job templates).

### 6. Community and Vendor Support

Thousands of modules exist for cloud (AWS, Azure), networking (Cisco, Juniper), containers (Docker, Kubernetes), databases, and more — maintained by Red Hat and the community.

---

## Module vs Command/Shell

| Aspect | Module (`win_file`, `yum`, `copy`) | Command/Shell (`win_shell`, `command`) |
|--------|-------------------------------------|----------------------------------------|
| Idempotent | Yes (by design) | No (runs every time) |
| Declarative | Yes | Imperative (script-like) |
| Return format | Structured JSON | Raw stdout/stderr |
| Best for | Configuration management | One-off scripts, legacy tools |
| Change detection | Automatic | Manual (`changed_when:`) |

**Rule of thumb:** Prefer modules. Use `win_shell` or `command` only when no module exists for your task (e.g., running `setup.exe` for Office Deployment Tool).

---

## Module Categories

### System & Files

| Module | Purpose |
|--------|---------|
| `ansible.builtin.file` | Files, directories, symlinks (Linux) |
| `ansible.windows.win_file` | Files and directories (Windows) |
| `ansible.builtin.copy` | Copy files to Linux hosts |
| `ansible.windows.win_copy` | Copy files on/from Windows |
| `ansible.builtin.template` | Render Jinja2 templates (Linux) |
| `ansible.windows.win_template` | Render Jinja2 templates (Windows) |

### Package Management

| Module | Purpose |
|--------|---------|
| `ansible.builtin.yum` / `dnf` | RHEL/CentOS packages |
| `ansible.builtin.apt` | Debian/Ubuntu packages |
| `ansible.windows.win_package` | MSI/EXE installers |

### Services

| Module | Purpose |
|--------|---------|
| `ansible.builtin.service` | Start/stop/restart Linux services |
| `ansible.windows.win_service` | Manage Windows services |

### Information & Debugging

| Module | Purpose |
|--------|---------|
| `ansible.builtin.setup` | Gather facts (OS, IP, memory) |
| `ansible.windows.win_reg_stat` | Read Windows registry values |
| `ansible.windows.win_stat` | Check if file/path exists |
| `ansible.builtin.debug` | Print messages during playbook run |

### System Control

| Module | Purpose |
|--------|---------|
| `ansible.builtin.reboot` | Reboot Linux hosts |
| `ansible.windows.win_reboot` | Reboot Windows hosts |

---

## Anatomy of a Module Task

```yaml
- name: Copy setup.exe from LAN share to staging    # 1. Task name (human-readable)
  ansible.windows.win_copy:                          # 2. Module FQCN
    src: "{{ office2016_lan_source_path }}\\setup.exe"  # 3. Module parameters
    dest: "{{ office2016_staging_path }}\\setup.exe"
    remote_src: true
  register: copy_result                              # 4. Save output to variable
  when: office2016_lan_setup.stat.exists             # 5. Conditional execution
  failed_when: false                                 # 6. Custom failure logic (optional)
```

### FQCN (Fully Qualified Collection Name)

Format: `namespace.collection.module_name`

Examples:

- `ansible.builtin.debug` — built-in debug module
- `ansible.windows.win_file` — Windows file module from `ansible.windows` collection
- `amazon.aws.ec2_instance` — AWS EC2 module from `amazon.aws` collection

### Common Parameters (Many Modules)

| Parameter | Meaning |
|-----------|---------|
| `state` | Desired state: `present`, `absent`, `directory`, `file` |
| `path` / `dest` | File or directory path |
| `owner` / `group` / `mode` | Linux permissions |
| `src` | Source file path |

### Return Values (via `register`)

```yaml
- ansible.windows.win_reg_stat:
    path: HKLM:\SOFTWARE\Microsoft\Office\16.0\Common\InstallRoot
  register: office2016_reg

# Use later:
when: office2016_reg.exists | default(false)
```

---

## Idempotency Explained

**Idempotency** means: applying the same configuration multiple times produces the same result without unintended side effects.

### Example from Office 2016 Role

```yaml
- name: Check if Microsoft Office 2016 is already installed
  ansible.windows.win_reg_stat:
    path: HKLM:\SOFTWARE\Microsoft\Office\16.0\Common\InstallRoot
  register: office2016_reg

- name: Skip install when Office 2016 is already present
  when: not (office2016_reg.exists | default(false))
  block:
    # ... install steps only run if Office is NOT installed
```

**First run:** Office not installed → install block executes → `changed: true`  
**Second run:** Office detected in registry → install block skipped → `ok` (no changes)

This is why AWX can schedule weekly jobs safely — hosts with Office already installed are skipped automatically.

---

## Built-in vs Collection Modules

### Built-in Modules (`ansible.builtin.*`)

Shipped with Ansible core. No extra install required.

Examples: `debug`, `copy`, `file`, `yum`, `service`, `template`, `command`

### Collection Modules

Packaged separately via Ansible Galaxy. Defined in `requirements.yml`:

```yaml
collections:
  - name: ansible.windows
    version: ">=2.0.0"
```

Install with:

```bash
ansible-galaxy collection install -r requirements.yml
```

This project uses **ansible.windows** for all Windows-specific modules (`win_file`, `win_copy`, `win_reg_stat`, `win_reboot`, `win_template`, `win_shell`).

---

## Real-World Examples (This Project)

### Example 1: Check Registry (win_reg_stat)

```yaml
- name: Check if Microsoft Office 2016 is already installed
  ansible.windows.win_reg_stat:
    path: HKLM:\SOFTWARE\Microsoft\Office\16.0\Common\InstallRoot
    name: Path
  register: office2016_reg
  failed_when: false
```

**Why this module:** Reads Windows registry safely; returns `exists` and `value` for conditionals.

---

### Example 2: Create Directory (win_file)

```yaml
- name: Ensure Office staging directory exists
  ansible.windows.win_file:
    path: "{{ office2016_staging_path }}"
    state: directory
```

**Why this module:** Idempotent directory creation; no PowerShell script needed.

---

### Example 3: Verify File Exists (win_stat)

```yaml
- name: Verify LAN Office source path is reachable from target
  ansible.windows.win_stat:
    path: "{{ office2016_lan_source_path }}\\{{ office2016_setup_exe }}"
  register: office2016_lan_setup
  failed_when: not office2016_lan_setup.stat.exists
```

**Why this module:** Validates UNC path before copy; fails fast with clear error.

---

### Example 4: Copy from LAN Share (win_copy)

```yaml
- name: Copy setup.exe from LAN share to staging
  ansible.windows.win_copy:
    src: "{{ office2016_lan_source_path }}\\{{ office2016_setup_exe }}"
    dest: "{{ office2016_staging_path }}\\{{ office2016_setup_exe }}"
    remote_src: true
```

**Why this module:** Handles file copy on Windows with checksum comparison; skips if unchanged.

---

### Example 5: Generate Config from Template (win_template)

```yaml
- name: Generate configuration.xml when not on LAN share
  ansible.windows.win_template:
    src: configuration.xml.j2
    dest: "{{ office2016_staging_path }}\\{{ office2016_config_file }}"
  when: not office2016_lan_config.stat.exists
```

**Why this module:** Renders Jinja2 variables into XML on the remote Windows host.

---

### Example 6: Debug Output (debug)

```yaml
- name: Report successful installation
  ansible.builtin.debug:
    msg: "Office 2016 installed successfully at {{ office2016_verify.value }}"
```

**Why this module:** Displays human-readable messages in playbook output and AWX job logs.

---

### Example 7: Reboot When Required (win_reboot)

```yaml
- name: Reboot when Office setup requires it (exit code 3010)
  ansible.windows.win_reboot:
    reboot_timeout: 900
  when:
    - office2016_reboot in ['always', 'if_required']
    - office2016_install.rc == 3010
```

**Why this module:** Safely reboots Windows and waits for WinRM to come back online.

---

### Example 8: When No Module Exists (win_shell)

```yaml
- name: Install Microsoft Office 2016 silently via ODT
  ansible.windows.win_shell: |
    $setup = Join-Path -Path '{{ office2016_staging_path }}' -ChildPath '{{ office2016_setup_exe }}'
    $config = Join-Path -Path '{{ office2016_staging_path }}' -ChildPath '{{ office2016_config_file }}'
    $process = Start-Process -FilePath $setup -ArgumentList "/configure `"$config`"" -PassThru -Wait
    exit $process.ExitCode
  register: office2016_install
  failed_when: office2016_install.rc not in [0, 3010]
```

**Why shell here:** Microsoft ODT `setup.exe` has no dedicated Ansible module; shell wraps the installer and captures exit code.

---

## Best Practices

1. **Prefer modules over shell** — Use `win_copy` instead of `Copy-Item` in shell when possible.
2. **Use FQCN** — Write `ansible.builtin.debug` instead of bare `debug` for clarity and future compatibility.
3. **Name every task** — AWX logs and troubleshooting depend on clear task names.
4. **Register results** — Save module output when you need conditionals or verification.
5. **Use `when:` for conditionals** — Avoid running tasks unnecessarily.
6. **Set `failed_when:` carefully** — Treat expected exit codes (e.g., 3010 for reboot) as success.
7. **Install collections explicitly** — Pin versions in `requirements.yml` for reproducible AWX environments.
8. **Test idempotency** — Run playbooks twice; second run should show mostly `ok` not `changed`.

---

## Summary

| Concept | Key Takeaway |
|---------|--------------|
| **Module** | Reusable action unit that performs one job on managed nodes |
| **Why use modules** | Idempotency, declarative config, structured output, security |
| **FQCN** | `namespace.collection.module_name` (e.g., `ansible.windows.win_file`) |
| **Idempotency** | Safe to re-run; only changes what is needed |
| **Collections** | Extend Ansible with platform/cloud-specific modules |
| **This project** | Uses `ansible.windows` modules for LAN Office 2016 deployment via AWX |

---

## Further Reading

- [Ansible Module Index](https://docs.ansible.com/ansible/latest/collections/index_module.html)
- [ansible.windows Collection](https://docs.ansible.com/ansible/latest/collections/ansible/windows/)
- [Ansible Best Practices](https://docs.ansible.com/ansible/latest/tips_tricks/ansible_tips_tricks.html)
