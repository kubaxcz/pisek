interface StarInputProps {
  value: number | null;
  onChange: (value: number | null) => void;
  max?: number;
  label: string;
}

/** Selectable 1–max star rating; clicking the active value clears it. */
export function StarInput({ value, onChange, max = 5, label }: StarInputProps) {
  return (
    <div className="star-input" role="group" aria-label={label}>
      {Array.from({ length: max }, (_, i) => i + 1).map((n) => (
        <button
          type="button"
          key={n}
          className={'star-input__star' + (value !== null && n <= value ? ' is-on' : '')}
          aria-label={`${n}`}
          aria-pressed={value === n}
          onClick={() => onChange(value === n ? null : n)}
        >
          ★
        </button>
      ))}
      {value !== null ? <span className="star-input__value">{value}/{max}</span> : null}
    </div>
  );
}
