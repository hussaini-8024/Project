import cookieParser from "cookie-parser";
import cors from "cors";
import express from "express";
import helmet from "helmet";
import { env } from "./config/env.js";
import { errorHandler, notFound } from "./middleware/error.js";
import { router } from "./routes/index.js";

const app = express();

app.use(
  helmet({
    crossOriginResourcePolicy: { policy: "cross-origin" },
  }),
);
app.use(
  cors({
    origin: (origin, callback) => callback(null, origin || env.CLIENT_ORIGIN),
    credentials: true,
  }),
);
app.use(express.json({ limit: "2mb" }));
app.use(cookieParser());
app.use("/api", router);
app.use(notFound);
app.use(errorHandler);

app.listen(env.PORT, "0.0.0.0", () => {
  console.log(`UniMeet API listening on http://0.0.0.0:${env.PORT}`);
});
