import { CategoryStrip } from "@/components/CategoryStrip";
import { FeaturedCourses } from "@/components/FeaturedCourses";
import { Hero } from "@/components/Hero";
import { RolesSection } from "@/components/RolesSection";
import { SiteFooter } from "@/components/SiteFooter";
import { SiteHeader } from "@/components/SiteHeader";

export default function Home() {
  return (
    <>
      <SiteHeader />
      <main className="flex-1">
        <Hero />
        <CategoryStrip />
        <FeaturedCourses />
        <RolesSection />
      </main>
      <SiteFooter />
    </>
  );
}
