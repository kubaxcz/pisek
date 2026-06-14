interface StarsProps {
  value: number;
  max?: number;
}

/** Renders `value` filled stars (clamped to max). */
export function Stars({ value, max = 5 }: StarsProps) {
  const filled = Math.max(0, Math.min(max, Math.round(value)));
  if (filled <= 0) {
    return null;
  }
  return (
    <span className="stars" title={`${filled}/${max}`}>
      {'★'.repeat(filled)}
    </span>
  );
}
