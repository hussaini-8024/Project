import { Hero } from "@/components/Hero";
import { Reviews } from "@/components/Reviews";
import { SiteFooter } from "@/components/SiteFooter";
import { SiteHeader } from "@/components/SiteHeader";
import { TopCourses } from "@/components/TopCourses";
import { WhatYoullLearn } from "@/components/WhatYoullLearn";
import { topCourses } from "@/lib/courses";

export default function Home() {
  return (
    <>
      <SiteHeader variant="overlay" />
      <main className="flex-1">
        <Hero />
        <TopCourses courses={topCourses} />
        <WhatYoullLearn />
        <Reviews />
      </main>
      <SiteFooter />
    </>
  );
}
