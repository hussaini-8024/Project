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
};

export const featuredCourses: Course[] = [
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
  },
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
