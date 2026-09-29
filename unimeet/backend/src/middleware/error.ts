import type { NextFunction, Request, Response } from "express";
import { ZodError } from "zod";
import { HttpError } from "../utils/httpError.js";

export function notFound(_req: Request, _res: Response, next: NextFunction) {
  next(new HttpError(404, "Route not found.", "not_found"));
}

export function errorHandler(err: unknown, _req: Request, res: Response, _next: NextFunction) {
  if (err instanceof ZodError) {
    res.status(400).json({
      error: "validation_error",
      message: "Invalid request.",
      details: err.issues,
    });
    return;
  }

  if (err instanceof HttpError) {
    res.status(err.status).json({
      error: err.code,
      message: err.message,
      ...err.extra,
    });
    return;
  }

  console.error(err);
  res.status(500).json({
    error: "server_error",
    message: "Something went wrong.",
  });
}
