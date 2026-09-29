"use client";

import Link from "next/link";
import { useState } from "react";

type LoginMenuProps = {
  tone?: "light" | "dark";
};

export function LoginMenu({ tone = "dark" }: LoginMenuProps) {
  const [open, setOpen] = useState(false);
  const light = tone === "light";

  return (
    <div
      className="relative"
      onMouseEnter={() => setOpen(true)}
      onMouseLeave={() => setOpen(false)}
      onFocus={() => setOpen(true)}
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
          setOpen(false);
        }
      }}
    >
      <button
        type="button"
        className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition ${
          light
            ? "text-white/90 hover:bg-white/10 hover:text-white"
            : "text-ink hover:bg-mist"
        }`}
        aria-expanded={open}
        aria-haspopup="menu"
      >
        Log in
        <svg
          width="12"
          height="12"
          viewBox="0 0 12 12"
          fill="none"
          aria-hidden
          className={`transition ${open ? "rotate-180" : ""}`}
        >
          <path
            d="M2.5 4.5 6 8l3.5-3.5"
            stroke="currentColor"
            strokeWidth="1.5"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
      </button>

      <div
        role="menu"
        className={`absolute right-0 top-full z-50 min-w-[12.5rem] pt-2 transition ${
          open ? "visible opacity-100" : "invisible opacity-0"
        }`}
      >
        <div className="overflow-hidden rounded-xl bg-white py-1 shadow-xl shadow-black/10 ring-1 ring-[var(--line)]">
          <Link
            role="menuitem"
            href="/login?role=student"
            className="block px-4 py-2.5 text-sm font-medium text-ink transition hover:bg-mist"
          >
            As Student
          </Link>
          <Link
            role="menuitem"
            href="/login?role=teacher"
            className="block px-4 py-2.5 text-sm font-medium text-ink transition hover:bg-mist"
          >
            As Teacher
          </Link>
        </div>
      </div>
    </div>
  );
}
