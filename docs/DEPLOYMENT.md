# Production deployment — University Cyber Range

Target host: Ubuntu 22.04/24.04 or Debian 12, 128 GB RAM, 3.2 TB storage, KVM + Docker.

## 1. Separate the planes

| Plane | Components | Students |
| --- | --- | --- |
| Management | nginx, FastAPI, PostgreSQL, Redis, UI | HTTPS only |
| Workload | Docker engine, libvirt/KVM, student bridges | never exposed |

Do not mount `/var/run/docker.sock` or `/var/run/libvirt/libvirt-sock` into student containers.

## 2. Host packages

```bash
sudo apt update
sudo apt install -y qemu-kvm libvirt-daemon-system libvirt-clients bridge-utils \
  docker.io docker-compose-plugin nginx postgresql redis-server certbot python3-certbot-nginx
sudo usermod -aG kvm,libvirt,docker $USER
```

Confirm `/dev/kvm` exists. Enable IP forwarding and disable inter-lab forwarding by default:

```bash
echo 'net.ipv4.ip_forward=1' | sudo tee /etc/sysctl.d/99-cyberrange.conf
sudo sysctl --system
```

## 3. Storage layout (3.2 TB example)

```text
/var/lib/cyberrange/images      shared container + VM templates (CoW / backing files)
/var/lib/cyberrange/volumes     persistent student data
/var/lib/cyberrange/vms         thin-provisioned qcow2
/var/lib/cyberrange/isos        administrator ISO repository
/var/lib/cyberrange/backups     database + metadata + selected disks
/var/log/cyberrange             rotated logs
```

Apply filesystem quotas per category. Snapshot count is enforced in software (`snapshot_max_per_student`).

## 4. Configuration

Copy `.env.example` and set:

- `SECRET_KEY` — `openssl rand -hex 32`
- `DATABASE_URL` — PostgreSQL
- `COMPUTE_PROVIDER=hybrid`
- `HOST_TOTAL_RAM_MB=131072`
- `HOST_RESERVE_RAM_MB=20480` (16–24 GB is appropriate)
- `COOKIE_SECURE=true`
- `CORS_ORIGINS=https://range.university.edu`

Reserve RAM for the host, database, Redis, API, Docker, libvirt, and caches. Never allocate 100% of RAM to labs.

## 5. TLS

Terminate TLS on nginx or Traefik. Forward `/api` and `/ws` to the API. Use HTTP-only cookies and HSTS.

## 6. Network isolation

For each student lab the platform records:

- dedicated Linux network namespace
- bridge / VLAN id
- RFC1918 CIDR
- default-deny between labs
- optional controlled NAT for internet

Add nftables/iptables rules that drop:

- lab → other lab bridges
- lab → host management addresses
- lab → Docker/libvirt sockets

Internet, when enabled by staff, is SNAT only.

## 7. Backups and recovery

| Asset | Method | Retention |
| --- | --- | --- |
| PostgreSQL | `pg_dump` via `/api/backups` + cron | 14 days (configurable) |
| Lab metadata | included in database | 14 days |
| Persistent volumes | rsync/zfs send of `/var/lib/cyberrange/volumes` | course term |
| VM disks | copy-on-write snapshots, not every ephemeral container | policy |
| Templates / ISOs | replicate the approved catalog | long term |

Test restore quarterly: database recovery, lab metadata recovery, one VM disk recovery.

Do not back up ephemeral exercise containers.

## 8. Multi-node (future)

The scheduler already selects a healthy `ComputeNode` by RAM, CPU, KVM/Docker availability, and current load. Add rows to `compute_nodes` and run a worker on each host. The first generation is a single controller+worker (`node-01`). Additional nodes do not require an application rewrite.

## 9. Load testing

From an administrator session:

```http
POST /api/resources/loadtest
```

Or:

```bash
python loadtest/run.py --api http://127.0.0.1:8000 --user admin --password 'CyberRange!Admin2026'
```

Use the printed `SAFE_*` fields as the production concurrency policy. Re-run after hardware or image changes.

## 10. Hardening checklist

- [ ] MFA enabled for all administrators
- [ ] Demo passwords rotated
- [ ] ISO uploads limited and checksummed
- [ ] Student outbound internet default-deny
- [ ] Audit log shipped to a write-once store
- [ ] Host reserve and 90% emergency threshold confirmed
- [ ] noVNC/SPICE gateway bound to localhost behind nginx
- [ ] SSH to the hypervisor limited to operators
