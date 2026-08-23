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

    cors_origins: str = "http://localhost:5173,http://127.0.0.1:5173"

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

    cookie_secure: bool = False
    cookie_samesite: str = "lax"

    rate_limit_login: int = 8
    lockout_minutes: int = 15

    # AUKC "AU Kamra AI Agent" — OpenAI-compatible chat proxy.
    # Leave the key blank for graceful offline behaviour (no outbound calls).
    openai_api_key: str = ""
    aukc_ai_api_key: str = ""
    aukc_ai_model: str = "gpt-4o-mini"
    aukc_ai_base_url: str = "https://api.openai.com/v1"
    aukc_ai_timeout_seconds: float = 20.0

    @property
    def aukc_key(self) -> str:
        """Effective AI key: OPENAI_API_KEY takes precedence, then AUKC_AI_API_KEY."""
        return (self.openai_api_key or self.aukc_ai_api_key or "").strip()

    @property
    def cors_origin_list(self) -> list[str]:
        return [o.strip() for o in self.cors_origins.split(",") if o.strip()]

    @property
    def lab_pool_ram_mb(self) -> int:
        return max(0, self.host_total_ram_mb - self.host_reserve_ram_mb)

    @property
    def lab_pool_storage_gb(self) -> int:
        return max(0, self.host_total_storage_gb - self.host_reserve_storage_gb)


@lru_cache
def get_settings() -> Settings:
    return Settings()
