// src/types.ts or wherever your @/types path points to
import { ComponentType } from 'react';

export interface NavItem {
  title: string;
  href: string;
  icon?: ComponentType;
}