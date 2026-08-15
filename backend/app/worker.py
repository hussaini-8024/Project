"""Background worker: drain the lab queue and persist host samples."""

import time

from app.database import Base, SessionLocal, engine
from app.services.capacity import persist_sample, sample_host
from app.services.scheduler import drain_queue


def main() -> None:
    Base.metadata.create_all(bind=engine)
    while True:
        db = SessionLocal()
        try:
            drain_queue(db)
            persist_sample(db, sample_host())
        finally:
            db.close()
        time.sleep(5)


if __name__ == "__main__":
    main()
