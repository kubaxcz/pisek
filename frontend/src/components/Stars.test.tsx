import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Stars } from './Stars';

describe('Stars', () => {
  it('renders nothing for zero', () => {
    const { container } = render(<Stars value={0} />);
    expect(container.textContent).toBe('');
  });

  it('renders one star', () => {
    const { container } = render(<Stars value={1} />);
    expect(container.textContent).toBe('★');
  });

  it('renders five stars', () => {
    const { container } = render(<Stars value={5} />);
    expect(container.textContent).toBe('★★★★★');
  });

  it('respects an explicit max', () => {
    const { container } = render(<Stars value={5} max={2} />);
    expect(container.textContent).toBe('★★');
  });
});
