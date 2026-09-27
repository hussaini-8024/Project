export function formatMinutes(seconds: number) {
  return `${Math.round((seconds || 0) / 60)} min`;
}

export function formatPercent(value: number) {
  return `${Number(value || 0).toFixed(2)}%`;
}

export function formatWhen(value?: string | null) {
  return value ? new Date(value).toLocaleString() : "—";
}
