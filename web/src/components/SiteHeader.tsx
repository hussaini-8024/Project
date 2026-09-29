import Link from "next/link";
import { LoginMenu } from "@/components/LoginMenu";
import { Logo } from "@/components/Logo";

const links = [
  { href: "/", label: "Home" },
  { href: "/explore", label: "Explore" },
  { href: "/about", label: "About us" },
  { href: "/contact", label: "Contact us" },
];

type SiteHeaderProps = {
  variant?: "overlay" | "solid";
};

export function SiteHeader({ variant = "solid" }: SiteHeaderProps) {
  const overlay = variant === "overlay";

  return (
    <header
      className={
        overlay
          ? "absolute inset-x-0 top-0 z-30"
          : "sticky top-0 z-30 border-b border-[var(--line)] bg-white/90 backdrop-blur"
      }
    >
      <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-4 sm:px-8">
        <Logo tone={overlay ? "light" : "dark"} />

        <nav
          className={`hidden items-center gap-7 text-sm font-medium md:flex ${
            overlay ? "text-white/85" : "text-ink-muted"
          }`}
        >
          {links.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className={`transition ${overlay ? "hover:text-white" : "hover:text-brand"}`}
            >
              {link.label}
            </Link>
          ))}
        </nav>

        <div className="flex items-center gap-1 sm:gap-2">
          <LoginMenu tone={overlay ? "light" : "dark"} />
          <Link
            href="/signup"
            className={`rounded-lg px-3.5 py-2 text-sm font-semibold transition ${
              overlay
                ? "bg-accent text-brand-deep hover:bg-[#f0ad55]"
                : "bg-brand text-white hover:bg-brand-deep"
            }`}
          >
            Join for free
          </Link>
        </div>
      </div>
    </header>
  );
}
