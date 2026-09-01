from __future__ import annotations

import asyncio
import fcntl
import os
import pty
import struct
import termios
from collections.abc import Callable


class PtySession:
    def __init__(self, argv: list[str], env: dict[str, str] | None = None) -> None:
        self.master_fd, slave_fd = pty.openpty()
        pid = os.fork()
        if pid == 0:
            os.close(self.master_fd)
            os.setsid()
            os.dup2(slave_fd, 0)
            os.dup2(slave_fd, 1)
            os.dup2(slave_fd, 2)
            if slave_fd > 2:
                os.close(slave_fd)
            os.environ.update(env or {})
            os.environ.setdefault("TERM", "xterm-256color")
            try:
                os.execvp(argv[0], argv)
            except Exception:
                os._exit(127)
        os.close(slave_fd)
        self.pid = pid
        flags = fcntl.fcntl(self.master_fd, fcntl.F_GETFL)
        fcntl.fcntl(self.master_fd, fcntl.F_SETFL, flags | os.O_NONBLOCK)

    def resize(self, cols: int, rows: int) -> None:
        try:
            fcntl.ioctl(self.master_fd, termios.TIOCSWINSZ, struct.pack("HHHH", rows, cols, 0, 0))
        except OSError:
            pass

    def write(self, data: bytes) -> None:
        os.write(self.master_fd, data)

    def read(self) -> bytes:
        try:
            return os.read(self.master_fd, 4096)
        except BlockingIOError:
            return b""
        except OSError:
            return b""

    def close(self) -> None:
        try:
            os.close(self.master_fd)
        except OSError:
            pass
        try:
            os.kill(self.pid, 15)
        except OSError:
            pass


async def bridge_pty(session: PtySession, on_output: Callable[[str], asyncio.Future | None], stop: asyncio.Event) -> None:
    loop = asyncio.get_running_loop()

    def _ready() -> None:
        data = session.read()
        if data:
            text = data.decode("utf-8", errors="replace")
            result = on_output(text)
            if asyncio.iscoroutine(result):
                asyncio.create_task(result)

    loop.add_reader(session.master_fd, _ready)
    try:
        await stop.wait()
    finally:
        loop.remove_reader(session.master_fd)
        session.close()
