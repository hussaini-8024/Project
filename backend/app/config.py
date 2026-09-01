from functools import lru_cache
from typing import Literal

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    app_name: str = "University Cyber Range"
    app_env: Literal["development", "production", "test"] = "development"
    secret_key: str = "change-me-in-production-use-openssl-rand-hex-32"
    algorithm: str = "HS256"
    access_token_minutes: int = 30
    refresh_token_days: int = 7
    session_idle_minutes: int = 60

    database_url: str = "sqlite:///./data/cyberrange.db"
    redis_url: str = "redis://localhost:6379/0"

    cors_origins: str = (
        "http://localhost:18080,http://127.0.0.1:18080,"
        "http://localhost:18081,http://127.0.0.1:18081,"
        "http://172.26.1.3:18080,http://172.26.1.3:18081,"
        "http://172.30.0.2:18080,http://172.30.0.2:18081"
    )
    # Allow login/API from other PCs on the same LAN (private RFC1918 / localhost).
    cors_allow_lan: bool = True
    # Extra address to advertise on the login page (your Ubuntu LAN IP).
    public_host: str = "172.26.1.3"
    public_ui_port: int = 18080

    # Host reserve — never allocate 100% of RAM to student labs
    host_total_ram_mb: int = 131072  # 128 GB
    host_total_storage_gb: int = 3276  # 3.2 TB
    host_reserve_ram_mb: int = 20480  # 20 GB platform reserve
    host_reserve_storage_gb: int = 200
    host_cpu_cores: int = 32

    # Capacity thresholds (percent of lab pool / host)
    threshold_normal: int = 80
    threshold_warning: int = 85
    threshold_high: int = 90
    threshold_block: int = 90

    # Provider: mock for development, hybrid/live for production
    compute_provider: Literal["auto", "mock", "namespace", "docker", "libvirt", "hybrid"] = "auto"
    docker_socket: str = "unix:///var/run/docker.sock"
    libvirt_uri: str = "qemu:///system"

    storage_root: str = "./data/storage"
    iso_max_gb: int = 20
    snapshot_max_per_student: int = 3
    backup_retention_days: int = 14
    ephemeral_ttl_hours: int = 8

    # AUKC "AU Kamra AI Agent" — offline PDF book library search.
    # Max size (MB) for an admin-uploaded PDF book. Fully self-contained, no external AI.
    aukc_book_max_mb: int = 50

    cookie_secure: bool = False
    cookie_samesite: str = "lax"

    rate_limit_login: int = 8
    lockout_minutes: int = 15

    @property
    def cors_origin_list(self) -> list[str]:
        return [o.strip() for o in self.cors_origins.split(",") if o.strip()]

    @property
    def cors_origin_regex(self) -> str | None:
        if not self.cors_allow_lan:
            return None
        # http(s)://localhost | 127.0.0.1 | 10/8 | 172.16/12 | 192.168/16 | *.local, optional port
        return (
            r"https?://("
            r"localhost|"
            r"127\.0\.0\.1|"
            r"\[::1\]|"
            r"10(?:\.\d{1,3}){3}|"
            r"192\.168(?:\.\d{1,3}){2}|"
            r"172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2}|"
            r"[a-zA-Z0-9.-]+\.local"
            r")(?::\d+)?"
        )

    @property
    def lab_pool_ram_mb(self) -> int:
        return max(0, self.host_total_ram_mb - self.host_reserve_ram_mb)

    @property
    def lab_pool_storage_gb(self) -> int:
        return max(0, self.host_total_storage_gb - self.host_reserve_storage_gb)


@lru_cache
def get_settings() -> Settings:
    return Settings()
