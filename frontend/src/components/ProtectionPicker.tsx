import type { ProtectionType } from '../types';

export const PROTECTION_LABELS: Record<ProtectionType, string> = {
  kruh: 'Kruh',
  uzel: 'Uzel',
  hodiny: 'Hodiny',
  hrot: 'Hrot',
  strom: 'Strom',
  jine: 'Jiné',
};

interface ProtectionPickerProps {
  options: ProtectionType[];
  value: ProtectionType[];
  onChange: (value: ProtectionType[]) => void;
}

/**
 * Builds an ordered sequence of placed protection. Clicking an option appends
 * it; the sequence below preserves order and items can be removed.
 */
export function ProtectionPicker({ options, value, onChange }: ProtectionPickerProps) {
  const add = (t: ProtectionType) => onChange([...value, t]);
  const removeAt = (i: number) => onChange(value.filter((_, idx) => idx !== i));

  return (
    <div className="protection">
      <div className="protection__options">
        {options.map((t) => (
          <button type="button" key={t} className="protection__opt" onClick={() => add(t)}>
            + {PROTECTION_LABELS[t]}
          </button>
        ))}
      </div>
      {value.length > 0 ? (
        <ol className="protection__seq" aria-label="Pořadí jištění">
          {value.map((t, i) => (
            <li key={`${t}-${i}`} className="protection__chip">
              <span>
                {i + 1}. {PROTECTION_LABELS[t]}
              </span>
              <button type="button" aria-label="Odebrat" onClick={() => removeAt(i)}>
                ×
              </button>
            </li>
          ))}
        </ol>
      ) : (
        <p className="muted">Klikni na typ jištění v pořadí, jak se zakládalo.</p>
      )}
    </div>
  );
}

/** Read-only display of a protection sequence (for other users' entries). */
export function ProtectionSequence({ value }: { value: ProtectionType[] }) {
  if (value.length === 0) return null;
  return (
    <span className="protection__readonly">
      🛡 {value.map((t, i) => `${i + 1}.${PROTECTION_LABELS[t]}`).join(' → ')}
    </span>
  );
}
