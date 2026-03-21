interface RippleData {
  enabled?: boolean;
  centered?: boolean;
  circle?: boolean;
  class?: string;
  touched?: boolean;
}

declare global {
  interface HTMLElement {
    _ripple?: RippleData;
  }
}

export {};
