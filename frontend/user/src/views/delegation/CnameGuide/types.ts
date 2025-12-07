import type { DelegationItem } from "@/api/delegation";

export interface CnameGuideOptions {
  cname_to: {
    host: string;
    value: string;
    ttl?: number;
  };
  zone: string;
}

export interface CnameGuideProps {
  modelValue?: boolean;
  options?: DelegationItem | CnameGuideOptions | null;
}
