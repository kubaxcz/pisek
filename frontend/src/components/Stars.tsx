interface StarsProps {
  value: number;
}

/** Renders the route quality as 0–2 filled stars. */
export function Stars({ value }: StarsProps) {
  if (value <= 0) {
    return null;
  }
  const filled = Math.min(2, value);
  return (
    <span className="stars" title={`${filled} ${filled === 1 ? 'hvězdička' : 'hvězdičky'}`}>
      {'★'.repeat(filled)}
    </span>
  );
}
