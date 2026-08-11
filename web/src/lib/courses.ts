export type Course = {
  id: string;
  title: string;
  instructor: string;
  category: string;
  level: "Beginner" | "Intermediate" | "Advanced";
  rating: number;
  students: string;
  duration: string;
  cover: string;
  summary: string;
};

export const topCourses: Course[] = [
  {
    id: "1",
    title: "Product Strategy for Modern Teams",
    instructor: "Amina Okoro",
    category: "Business",
    level: "Intermediate",
    rating: 4.8,
    students: "42k",
    duration: "6 weeks",
    cover: "linear-gradient(135deg, #0f5c56, #1a7a72 45%, #e89b3c)",
    summary: "Ship sharper roadmaps with frameworks used by top product orgs.",
  },
  {
    id: "2",
    title: "Full-Stack Web Foundations",
    instructor: "Diego Alvarez",
    category: "Technology",
    level: "Beginner",
    rating: 4.9,
    students: "118k",
    duration: "8 weeks",
    cover: "linear-gradient(140deg, #163f4a, #0f5c56 40%, #4fb3a8)",
    summary: "Build real apps from HTML to APIs with guided projects.",
  },
  {
    id: "3",
    title: "Data Storytelling with Python",
    instructor: "Priya Nair",
    category: "Data Science",
    level: "Intermediate",
    rating: 4.7,
    students: "67k",
    duration: "5 weeks",
    cover: "linear-gradient(145deg, #0a3d39, #2d6a4f 50%, #95d5b2)",
    summary: "Turn raw datasets into clear narratives leaders act on.",
  },
];

export const allCourses: Course[] = [
  ...topCourses,
  {
    id: "4",
    title: "UX Research in Practice",
    instructor: "Elena Rossi",
    category: "Design",
    level: "Beginner",
    rating: 4.8,
    students: "31k",
    duration: "4 weeks",
    cover: "linear-gradient(135deg, #1b4332, #40916c 48%, #e89b3c)",
    summary: "Interview users, synthesize insights, and validate designs.",
  },
  {
    id: "5",
    title: "Digital Marketing Essentials",
    instructor: "Jordan Lee",
    category: "Marketing",
    level: "Beginner",
    rating: 4.6,
    students: "54k",
    duration: "5 weeks",
    cover: "linear-gradient(140deg, #245c55, #4a8f86 50%, #e89b3c)",
    summary: "Grow channels with campaigns that convert and retain.",
  },
  {
    id: "6",
    title: "Cloud Architecture Basics",
    instructor: "Samira Hassan",
    category: "Technology",
    level: "Advanced",
    rating: 4.9,
    students: "28k",
    duration: "7 weeks",
    cover: "linear-gradient(145deg, #0f4c5c, #3d7a7a 45%, #95d5b2)",
    summary: "Design scalable systems with modern cloud patterns.",
  },
];

export const learningOutcomes = [
  {
    title: "Job-ready skills",
    body: "Practice with projects that mirror real workplace challenges — not just theory slides.",
  },
  {
    title: "Expert instructors",
    body: "Learn from teachers who publish under their own ID and bring industry experience.",
  },
  {
    title: "Flexible pacing",
    body: "Study when it fits your life. Pause, resume, and track progress across devices.",
  },
  {
    title: "Recognized credentials",
    body: "Earn certificates you can share after completing purchased student courses.",
  },
];

export const reviews = [
  {
    name: "Maya Chen",
    role: "Student · Product Strategy",
    quote:
      "I landed a PM interview within weeks. The course structure is clear and the projects actually prepared me.",
    rating: 5,
  },
  {
    name: "Hassan Ali",
    role: "Student · Full-Stack Web",
    quote:
      "Best decision for switching careers. Labs felt practical and the community kept me accountable.",
    rating: 5,
  },
  {
    name: "Sofia Mendes",
    role: "Student · Data Storytelling",
    quote:
      "I finally present data with confidence. Managers notice the difference in my reports.",
    rating: 5,
  },
];

export const categories = [
  { name: "Technology", courses: "1,240+", tone: "from-[#0f5c56] to-[#1a7a72]" },
  { name: "Business", courses: "860+", tone: "from-[#163f4a] to-[#2a6f6a]" },
  { name: "Data Science", courses: "720+", tone: "from-[#0a3d39] to-[#2d6a4f]" },
  { name: "Design", courses: "540+", tone: "from-[#1b4332] to-[#52796f]" },
  { name: "Marketing", courses: "410+", tone: "from-[#245c55] to-[#4a8f86]" },
  { name: "Health", courses: "390+", tone: "from-[#0f4c5c] to-[#3d7a7a]" },
];

/** Update this with your real WhatsApp business number (country code, no +). */
export const WHATSAPP_NUMBER = "15551234567";
