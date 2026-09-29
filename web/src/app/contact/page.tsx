import { SiteFooter } from "@/components/SiteFooter";
import { SiteHeader } from "@/components/SiteHeader";
import { WHATSAPP_NUMBER } from "@/lib/courses";

export const metadata = {
  title: "Contact us — Stride",
  description: "Get in touch with the Stride team via form, email, or WhatsApp.",
};

const whatsappHref = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(
  "Hi Stride team — I have a question about your courses.",
)}`;

export default function ContactPage() {
  return (
    <>
      <SiteHeader />
      <main className="flex-1 bg-surface">
        <section className="border-b border-[var(--line)] bg-mist px-5 py-14 sm:px-8">
          <div className="mx-auto max-w-3xl">
            <h1 className="font-[family-name:var(--font-display)] text-4xl font-semibold tracking-tight text-ink sm:text-5xl">
              Contact us
            </h1>
            <p className="mt-5 text-lg leading-relaxed text-ink-muted">
              Questions about courses, teaching on Stride, or your account? Send a message or chat with us on WhatsApp.
            </p>
          </div>
        </section>

        <section className="px-5 py-14 sm:px-8">
          <div className="mx-auto grid max-w-6xl gap-10 lg:grid-cols-[1.2fr_0.8fr]">
            <form className="rounded-2xl bg-white p-6 ring-1 ring-[var(--line)] sm:p-8">
              <div className="grid gap-5 sm:grid-cols-2">
                <label className="block text-sm font-medium text-ink">
                  Name
                  <input
                    name="name"
                    required
                    className="mt-2 w-full rounded-xl border border-[var(--line)] bg-surface px-4 py-3 text-ink outline-none focus:border-brand"
                    placeholder="Your name"
                  />
                </label>
                <label className="block text-sm font-medium text-ink">
                  Email
                  <input
                    type="email"
                    name="email"
                    required
                    className="mt-2 w-full rounded-xl border border-[var(--line)] bg-surface px-4 py-3 text-ink outline-none focus:border-brand"
                    placeholder="you@example.com"
                  />
                </label>
              </div>
              <label className="mt-5 block text-sm font-medium text-ink">
                Message
                <textarea
                  name="message"
                  required
                  rows={5}
                  className="mt-2 w-full resize-y rounded-xl border border-[var(--line)] bg-surface px-4 py-3 text-ink outline-none focus:border-brand"
                  placeholder="How can we help?"
                />
              </label>
              <button
                type="submit"
                className="mt-6 rounded-xl bg-brand px-6 py-3 text-sm font-semibold text-white transition hover:bg-brand-deep"
              >
                Send message
              </button>
              <p className="mt-3 text-xs text-ink-muted">
                Form submit will be wired to backend in a later step.
              </p>
            </form>

            <aside className="space-y-6">
              <div className="rounded-2xl bg-white p-6 ring-1 ring-[var(--line)]">
                <h2 className="text-lg font-semibold text-ink">Chat on WhatsApp</h2>
                <p className="mt-2 text-sm leading-relaxed text-ink-muted">
                  Prefer a quick reply? Message our support team on WhatsApp — we typically respond during business hours.
                </p>
                <a
                  href={whatsappHref}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="mt-5 inline-flex items-center gap-2 rounded-xl bg-[#25D366] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#1ebe57]"
                >
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden>
                    <path d="M20.52 3.48A11.86 11.86 0 0 0 12.06 0C5.5 0 .16 5.34.16 11.9c0 2.1.55 4.15 1.6 5.96L0 24l6.3-1.65a11.86 11.86 0 0 0 5.76 1.47h.01c6.56 0 11.9-5.34 11.9-11.9 0-3.18-1.24-6.17-3.45-8.44ZM12.07 21.15h-.01a9.26 9.26 0 0 1-4.72-1.3l-.34-.2-3.74.98 1-3.64-.22-.37a9.27 9.27 0 0 1-1.42-4.94c0-5.12 4.17-9.29 9.3-9.29a9.24 9.24 0 0 1 9.28 9.3c0 5.12-4.17 9.28-9.29 9.28Zm5.1-6.96c-.28-.14-1.65-.81-1.9-.9-.26-.1-.44-.14-.63.14-.19.28-.72.9-.88 1.08-.16.19-.33.21-.6.07-.28-.14-1.17-.43-2.23-1.37-.82-.73-1.38-1.64-1.54-1.91-.16-.28-.02-.43.12-.57.13-.12.28-.33.42-.49.14-.16.19-.28.28-.47.1-.19.05-.35-.02-.49-.07-.14-.63-1.51-.86-2.07-.23-.55-.46-.47-.63-.48h-.54c-.19 0-.49.07-.75.35-.26.28-1 1-1 2.43s1.02 2.82 1.16 3.01c.14.19 2.01 3.07 4.87 4.31.68.29 1.21.47 1.62.6.68.21 1.3.18 1.79.11.55-.08 1.65-.67 1.88-1.32.23-.65.23-1.2.16-1.32-.07-.11-.25-.18-.53-.32Z" />
                  </svg>
                  WhatsApp us
                </a>
              </div>

              <div className="rounded-2xl bg-white p-6 ring-1 ring-[var(--line)]">
                <h2 className="text-lg font-semibold text-ink">Other ways to reach us</h2>
                <ul className="mt-3 space-y-2 text-sm text-ink-muted">
                  <li>
                    Email:{" "}
                    <a className="font-medium text-brand hover:text-brand-deep" href="mailto:hello@stride.learn">
                      hello@stride.learn
                    </a>
                  </li>
                  <li>Hours: Mon–Fri, 9:00–18:00</li>
                </ul>
              </div>
            </aside>
          </div>
        </section>
      </main>
      <SiteFooter />
    </>
  );
}
