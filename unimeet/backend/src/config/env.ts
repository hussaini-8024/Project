import dotenv from "dotenv";
import { z } from "zod";

dotenv.config();

const schema = z.object({
  PORT: z.coerce.number().default(4000),
  NODE_ENV: z.string().default("development"),
  DATABASE_URL: z.string().min(1),
  JWT_SECRET: z.string().min(16),
  JWT_EXPIRES_IN: z.string().default("12h"),
  CLIENT_ORIGIN: z.string().default("http://localhost:5173"),
  LIVEKIT_API_KEY: z.string().default("devkey"),
  LIVEKIT_API_SECRET: z.string().default("secret"),
  LIVEKIT_URL: z.string().default("ws://127.0.0.1:7880"),
  OPENAI_API_KEY: z.string().optional().default(""),
  ATTENDANCE_PRESENT_RATIO: z.coerce.number().default(0.75),
  ATTENDANCE_PARTIAL_RATIO: z.coerce.number().default(0.4),
  ATTENDANCE_PRESENT_MINUTES: z.coerce.number().default(45),
  ATTENDANCE_PARTIAL_MINUTES: z.coerce.number().default(20),
});

export const env = schema.parse(process.env);
