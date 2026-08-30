export class HttpError extends Error {
  status: number;
  code: string;
  extra?: Record<string, unknown>;

  constructor(
    status: number,
    message: string,
    code = "ERROR",
    extra?: Record<string, unknown>,
  ) {
    super(message);
    this.status = status;
    this.code = code;
    this.extra = extra;
  }
}

export function deny(code: string, message: string, status = 403) {
  return new HttpError(status, message, code);
}
