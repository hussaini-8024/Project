from sqlalchemy import create_engine, text
from sqlalchemy.orm import Session

from app.services.schema import migrate_network_schema


def test_rebuilds_unique_lab_id_constraint(tmp_path) -> None:
    engine = create_engine(f"sqlite:///{tmp_path / 'net.db'}")
    with engine.begin() as conn:
        conn.execute(
            text(
                """
                CREATE TABLE networks (
                    id VARCHAR(36) NOT NULL,
                    lab_id VARCHAR(36) NOT NULL,
                    name VARCHAR(64) NOT NULL,
                    cidr VARCHAR(32) NOT NULL,
                    vlan_id INTEGER NOT NULL,
                    namespace VARCHAR(64) NOT NULL,
                    isolated BOOLEAN NOT NULL,
                    internet BOOLEAN NOT NULL,
                    bridge VARCHAR(64) NOT NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE (lab_id)
                )
                """
            )
        )
        conn.execute(
            text(
                """
                INSERT INTO networks VALUES
                ('n1', 'lab1', 'net-a', '10.142.0.0/24', 1001, 'ns-a', 1, 0, 'bra', '2026-01-01')
                """
            )
        )
    migrate_network_schema(engine)
    with engine.connect() as conn:
        sql = conn.execute(text("SELECT sql FROM sqlite_master WHERE name='networks'")).scalar() or ""
        assert "UNIQUE (lab_id)" not in " ".join(sql.split())
        cols = [row[1] for row in conn.execute(text("PRAGMA table_info(networks)"))]
        assert "kind" in cols
        assert "created_by" in cols
        count = conn.execute(text("SELECT count(*) FROM networks")).scalar()
        assert count == 1
    engine.dispose()
