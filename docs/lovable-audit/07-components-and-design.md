# 07 — Components & Design

## Brand & design direction

- Dark, premium darts aesthetic (no casino/raffle look). Vault Green primary, Darts Red for urgency, gold accents (`darts-gold`) for functional highlights. Mobile-first (≈80% mobile traffic); no horizontal scrolling allowed.
- Fonts (self-hosted via `@fontsource`): Anton (display headings), Hanken Grotesk / Outfit (body/UI), JetBrains Mono (numbers, ticket numbers, timers). See `fontFamily` in tailwind config below; check `src/main.tsx` / `src/index.css` for which weights are imported.
- Note: Tailwind custom colour `base` was renamed to `canvas` because it collided with the `text-base` font-size utility.
- Theme is dark-only in practice (CSS variables in `:root`); `next-themes` is installed but no light theme is actively designed (unclear if any toggle exists — none found in navigation).

## tailwind.config.ts (verbatim)

```ts
import type { Config } from "tailwindcss";

export default {
  darkMode: ["class"],
  content: ["./pages/**/*.{ts,tsx}", "./components/**/*.{ts,tsx}", "./app/**/*.{ts,tsx}", "./src/**/*.{ts,tsx}"],
  prefix: "",
  theme: {
    container: {
      center: true,
      padding: "2rem",
      screens: {
        "2xl": "1400px",
      },
    },
    extend: {
      fontFamily: {
        sans: ['"Hanken Grotesk"', 'system-ui', 'sans-serif'],
        display: ['"Anton"', '"Hanken Grotesk"', 'system-ui', 'sans-serif'],
        // Used by the instant-win reveal components.
        body: ['"Hanken Grotesk"', 'system-ui', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
      },
      colors: {
        /* Instant-win reveal games (src/components/instant-win). */
        vn: {
          bg: "#0A0B0A",
          surface: "#111311",
          raised: "#181B18",
          band: "#0F110F",
          text: "#F5F7F5",
          green: "#22C55E",
          "green-light": "#4ADE80",
          "green-deep": "#16A34A",
          mint: "#34D399",
          "mint-deep": "#10B981",
          border: "rgba(255,255,255,0.08)",
        },
        border: "hsl(0 0% 100% / 0.08)",
        hairline: "hsl(0 0% 100% / 0.08)",
        input: "hsl(var(--input))",
        ring: "hsl(var(--ring))",
        background: "hsl(var(--background))",
        foreground: "hsl(var(--foreground))",
        ink: "hsl(var(--ink))",
        canvas: "hsl(var(--base))",
        surface: "hsl(var(--surface))",
        elevated: "hsl(var(--elevated))",
        urgent: "hsl(var(--darts-red))",
        vault: {
          DEFAULT: "hsl(var(--vault-green))",
          light: "hsl(var(--vault-green-light))",
          glow: "hsl(var(--vault-green-glow))",
          deep: "hsl(var(--vault-green-deep))",
          brt: "hsl(var(--vault-green-light))",
        },
        darts: {
          red: "hsl(var(--darts-red))",
          "red-light": "hsl(var(--darts-red-light))",
        },
        charcoal: {
          DEFAULT: "hsl(var(--charcoal))",
          light: "hsl(var(--charcoal-light))",
          lighter: "hsl(var(--charcoal-lighter))",
        },
        cream: {
          DEFAULT: "hsl(var(--cream))",
          muted: "hsl(var(--cream-muted))",
        },
        primary: {
          DEFAULT: "hsl(var(--primary))",
          foreground: "hsl(var(--primary-foreground))",
        },
        secondary: {
          DEFAULT: "hsl(var(--secondary))",
          foreground: "hsl(var(--secondary-foreground))",
        },
        destructive: {
          DEFAULT: "hsl(var(--destructive))",
          foreground: "hsl(var(--destructive-foreground))",
        },
        muted: {
          DEFAULT: "hsl(var(--muted))",
          foreground: "hsl(var(--muted-foreground))",
        },
        accent: {
          DEFAULT: "hsl(var(--accent))",
          foreground: "hsl(var(--accent-foreground))",
        },
        popover: {
          DEFAULT: "hsl(var(--popover))",
          foreground: "hsl(var(--popover-foreground))",
        },
        card: {
          DEFAULT: "hsl(var(--card))",
          foreground: "hsl(var(--card-foreground))",
        },
        sidebar: {
          DEFAULT: "hsl(var(--sidebar-background))",
          foreground: "hsl(var(--sidebar-foreground))",
          primary: "hsl(var(--sidebar-primary))",
          "primary-foreground": "hsl(var(--sidebar-primary-foreground))",
          accent: "hsl(var(--sidebar-accent))",
          "accent-foreground": "hsl(var(--sidebar-accent-foreground))",
          border: "hsl(var(--sidebar-border))",
          ring: "hsl(var(--sidebar-ring))",
        },
      },
      borderRadius: {
        lg: "var(--radius)",
        md: "calc(var(--radius) - 2px)",
        sm: "calc(var(--radius) - 4px)",
      },
      keyframes: {
        "accordion-down": {
          from: { height: "0" },
          to: { height: "var(--radix-accordion-content-height)" },
        },
        "accordion-up": {
          from: { height: "var(--radix-accordion-content-height)" },
          to: { height: "0" },
        },
        "fade-in": {
          from: { opacity: "0", transform: "translateY(10px)" },
          to: { opacity: "1", transform: "translateY(0)" },
        },
        "scale-in": {
          from: { opacity: "0", transform: "scale(0.95)" },
          to: { opacity: "1", transform: "scale(1)" },
        },
        "pulse-glow": {
          "0%, 100%": { boxShadow: "0 0 20px hsl(147 61% 27% / 0.3)" },
          "50%": { boxShadow: "0 0 40px hsl(147 61% 27% / 0.5)" },
        },
        shimmer: {
          from: { backgroundPosition: "200% 0" },
          to: { backgroundPosition: "-200% 0" },
        },
        marquee: {
          from: { transform: "translateX(0)" },
          to: { transform: "translateX(-50%)" },
        },
      },
      animation: {
        "accordion-down": "accordion-down 0.2s ease-out",
        "accordion-up": "accordion-up 0.2s ease-out",
        "fade-in": "fade-in 0.5s ease-out forwards",
        "scale-in": "scale-in 0.3s ease-out forwards",
        "pulse-glow": "pulse-glow 2s ease-in-out infinite",
        shimmer: "shimmer 8s ease-in-out infinite",
        marquee: "marquee 30s linear infinite",
      },
    },
  },
  plugins: [require("tailwindcss-animate")],
} satisfies Config;

```

## src/index.css (verbatim — global CSS, tokens, utilities, animations)

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

html, body {
  overflow-x: clip;
  width: 100%;
}

/* =========================================================
   Vault Nine — Premium Darts Competition Design System
   Black-first, white-led, Vault green as a rare signal colour.
   Anton (display) · Hanken Grotesk (UI/body) · JetBrains Mono (data)
   ========================================================= */

@layer base {
  :root {
    /* === Brand surfaces (dark, black-first) === */
    --ink:        0 0% 2%;        /* page background  #050505 */
    --base:       240 8% 4%;      /* primary surface  #070708 */
    --surface:    240 12% 6%;     /* cards            #0E0E11 */
    --elevated:   240 11% 9%;     /* raised           #15151A */
    --hairline:   0 0% 100%;      /* used with /8 alpha */

    /* === Vault green (used SPARINGLY) === */
    --vault-green:        143 100% 35%;   /* #00B050 primary  */
    --vault-green-light:  142 82% 46%;    /* #15D46B bright   */
    --vault-green-glow:   142 82% 60%;
    --vault-green-deep:   148 89% 11%;    /* #03371F          */

    /* === Urgency only === */
    --darts-red:        0 100% 63%;       /* #FF4242 */
    --darts-red-light:  0 100% 70%;

    /* === Type === */
    --text:        0 0% 100%;
    --text-muted:  240 5% 62%;            /* #9A9AA2 */
    --text-faint:  240 4% 39%;            /* #5E5E66 */

    /* Legacy aliases kept so existing components keep working */
    --charcoal: 0 0% 2%;
    --charcoal-light: 240 8% 4%;
    --charcoal-lighter: 240 12% 6%;
    --cream: 0 0% 100%;
    --cream-muted: 240 5% 62%;

    /* === Semantic tokens (shadcn) === */
    --background: var(--ink);
    --foreground: var(--text);

    --card: var(--surface);
    --card-foreground: var(--text);

    --popover: var(--elevated);
    --popover-foreground: var(--text);

    /* Primary = Vault green; foreground is near-black so text reads on the gradient */
    --primary: var(--vault-green);
    --primary-foreground: 142 60% 5%;     /* #04140B */

    --secondary: var(--elevated);
    --secondary-foreground: var(--text);

    --muted: var(--elevated);
    --muted-foreground: var(--text-muted);

    --accent: var(--vault-green-light);
    --accent-foreground: 142 60% 5%;

    --destructive: var(--darts-red);
    --destructive-foreground: 0 0% 100%;

    --border: 0 0% 100%;                   /* paired with /8 via --hairline usage */
    --input: 240 11% 9%;
    --ring: var(--vault-green);

    --radius: 0.875rem;

    /* === Gradients === */
    --gradient-vault: linear-gradient(180deg, hsl(142 82% 56%) 0%, hsl(143 100% 35%) 100%);
    --gradient-vault-soft: linear-gradient(135deg, hsl(143 100% 35% / 0.18) 0%, hsl(143 100% 35% / 0) 70%);
    --gradient-card: linear-gradient(180deg, hsl(240 12% 7%) 0%, hsl(240 12% 5%) 100%);
    --gradient-glass: linear-gradient(135deg, hsla(0 0% 100% / 0.06) 0%, hsla(0 0% 100% / 0.02) 100%);
    --gradient-hero: radial-gradient(ellipse 80% 60% at 20% 20%, hsl(143 100% 35% / 0.18) 0%, transparent 60%),
                     linear-gradient(90deg, hsl(0 0% 2%) 0%, hsl(0 0% 2% / 0.4) 60%, transparent 100%);

    /* === Shadows === */
    --shadow-glow:   0 10px 30px -8px hsl(143 100% 35% / 0.55), 0 0 0 1px hsl(143 100% 45% / 0.15) inset;
    --shadow-card:   0 1px 0 hsl(0 0% 100% / 0.04) inset, 0 12px 40px -16px hsl(0 0% 0% / 0.7);
    --shadow-btn:    0 8px 24px -8px hsl(143 100% 35% / 0.5);
    --shadow-elevated: 0 24px 60px -20px hsl(0 0% 0% / 0.8);

    /* Sidebar (admin) */
    --sidebar-background: var(--base);
    --sidebar-foreground: var(--text);
    --sidebar-primary: var(--vault-green);
    --sidebar-primary-foreground: 142 60% 5%;
    --sidebar-accent: var(--elevated);
    --sidebar-accent-foreground: var(--text);
    --sidebar-border: 0 0% 15%;
    --sidebar-ring: var(--vault-green);
  }

  .dark {
    /* Site is dark by default; .dark mirrors :root */
    --background: var(--ink);
    --foreground: var(--text);
    --card: var(--surface);
    --card-foreground: var(--text);
    --popover: var(--elevated);
    --popover-foreground: var(--text);
    --primary: var(--vault-green);
    --primary-foreground: 142 60% 5%;
    --secondary: var(--elevated);
    --secondary-foreground: var(--text);
    --muted: var(--elevated);
    --muted-foreground: var(--text-muted);
    --accent: var(--vault-green-light);
    --accent-foreground: 142 60% 5%;
    --destructive: var(--darts-red);
    --destructive-foreground: 0 0% 100%;
    --border: 0 0% 100%;
    --input: 240 11% 9%;
    --ring: var(--vault-green);
  }
}

@layer base {
  * {
    border-color: hsl(0 0% 100% / 0.08);   /* hairline default */
  }

  html {
    scroll-behavior: smooth;
    -webkit-text-size-adjust: 100%;
    text-size-adjust: 100%;
  }

  html, body {
    overflow-x: hidden;
    overscroll-behavior-y: none;
  }

  body {
    @apply bg-background text-foreground antialiased;
    font-family: 'Hanken Grotesk', system-ui, -apple-system, sans-serif;
    font-feature-settings: "ss01", "cv11";
    -webkit-tap-highlight-color: transparent;
  }

  /* Prevent iOS Safari auto-zoom on focus by ensuring inputs render at >=16px */
  @media (max-width: 640px) {
    input:not([type="checkbox"]):not([type="radio"]),
    select,
    textarea {
      font-size: 16px;
    }
  }

  /* Honor reduced-motion across the app */
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
      animation-duration: 0.001ms !important;
      animation-iteration-count: 1 !important;
      transition-duration: 0.001ms !important;
      scroll-behavior: auto !important;
    }
  }

  /* Headings default to body sans (Hanken Grotesk) so long-form pages
     (legal, account, dialogs) read cleanly. Athletic Anton display is
     opt-in via the .font-display utility used on hero/section titles. */
  h1, h2, h3, h4, h5, h6 {
    font-family: 'Hanken Grotesk', system-ui, -apple-system, sans-serif;
    letter-spacing: -0.01em;
    line-height: 1.15;
  }

  /* Numerals & data → JetBrains Mono scoreboard feel */
  .font-mono, [class*="tabular-nums"] {
    font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, monospace;
    font-feature-settings: "tnum", "zero";
  }

  button, a, input, textarea, select { outline: none; }
  button:focus, button:focus-visible,
  a:focus, a:focus-visible { outline: none; box-shadow: none; }
  button:focus-visible, a:focus-visible {
    box-shadow: 0 0 0 2px hsl(143 100% 35% / 0.5);
    border-radius: 0.5rem;
  }
}

@layer components {
  .vault-gradient    { background: var(--gradient-vault); }
  .vault-glow        { box-shadow: var(--shadow-glow); }
  .glass-card {
    background: var(--gradient-glass);
    backdrop-filter: blur(14px);
    border: 1px solid hsl(0 0% 100% / 0.08);
  }
  .text-gradient {
    background: var(--gradient-vault);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }
  .hero-glow         { background: var(--gradient-hero); }
  .hairline          { border-color: hsl(0 0% 100% / 0.08); }
  .surface-card {
    background: var(--gradient-card);
    border: 1px solid hsl(0 0% 100% / 0.06);
    box-shadow: var(--shadow-card);
  }

  /* Premium Vault green CTA — gradient + glow + dark text */
  .btn-vault {
    background-image: linear-gradient(180deg, #1ee07a 0%, #00B050 100%);
    color: #ffffff;
    box-shadow: var(--shadow-btn), inset 0 1px 0 hsl(140 100% 80% / 0.4);
    transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
  }
  .btn-vault:hover { transform: translateY(-1px); filter: brightness(1.05); box-shadow: 0 14px 36px -10px hsl(143 100% 35% / 0.7); }
  .btn-vault:active { transform: translateY(0); }

  /* Live dot — pulsing green */
  .live-dot {
    width: 8px; height: 8px; border-radius: 999px;
    background: hsl(142 82% 56%);
    box-shadow: 0 0 0 0 hsl(142 82% 56% / 0.6);
    animation: live-pulse 1.6s ease-out infinite;
  }
  /* Progress (sold %) hairline rail */
  .progress-rail {
    height: 4px; border-radius: 999px;
    background: hsl(0 0% 100% / 0.08);
    overflow: hidden;
  }
  .progress-fill {
    height: 100%;
    background: linear-gradient(90deg, hsl(143 100% 35%), hsl(142 82% 56%));
    box-shadow: 0 0 12px hsl(142 82% 56% / 0.5);
  }
}

@layer utilities {
  .animate-pulse-slow { animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
  .animate-float     { animation: float 6s ease-in-out infinite; }

  .scrollbar-hide::-webkit-scrollbar { display: none; }
  .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

  .safe-area-inset-bottom { padding-bottom: env(safe-area-inset-bottom, 0px); }
  .safe-area-inset-top    { padding-top: env(safe-area-inset-top, 0px); }

  .touch-target { min-height: 44px; min-width: 44px; }

  /* Anton display — single weight (400). Forces weight 400 so redundant
     font-bold/black/semibold modifiers in markup don't cause a fallback
     flicker before the webfont loads. Always uppercase. */
  .font-display {
    font-family: 'Anton', 'Hanken Grotesk', system-ui, sans-serif;
    font-weight: 400 !important;
    text-transform: uppercase;
    letter-spacing: 0.005em;
    line-height: 0.95;
  }
}

@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50%      { transform: translateY(-10px); }
}

@keyframes live-pulse {
  0%   { box-shadow: 0 0 0 0 hsl(142 82% 56% / 0.6); }
  70%  { box-shadow: 0 0 0 10px hsl(142 82% 56% / 0); }
  100% { box-shadow: 0 0 0 0 hsl(142 82% 56% / 0); }
}


```

## src/App.css (verbatim)

```css
#root {
  max-width: 1280px;
  margin: 0 auto;
  padding: 2rem;
  text-align: center;
}

.logo {
  height: 6em;
  padding: 1.5em;
  will-change: filter;
  transition: filter 300ms;
}
.logo:hover {
  filter: drop-shadow(0 0 2em #646cffaa);
}
.logo.react:hover {
  filter: drop-shadow(0 0 2em #61dafbaa);
}

@keyframes logo-spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: no-preference) {
  a:nth-of-type(2) .logo {
    animation: logo-spin infinite 20s linear;
  }
}

.card {
  padding: 2em;
}

.read-the-docs {
  color: #888;
}

```

## Reusable components (non-shadcn)

Props extracted from each file's `interface …Props` / `type …Props` declaration. `—` = no props interface found (component takes no props or uses inline typing).

### `src/components/NavLink.tsx`

Props: —

### `src/components/ProtectedRoute.tsx`

```ts
ProtectedRouteProps {
  children: ReactNode;
  requireAdmin?: boolean;
}
```

### `src/components/SEO.tsx`

```ts
SEOProps {
  title?: string;
  description?: string;
  canonical?: string;
  ogImage?: string;
  noIndex?: boolean;
  jsonLd?: Record<string, any>;
}
```

### `src/components/ScrollToTop.tsx`

Props: —

### `src/components/account/ResponsiblePlayPanel.tsx`

Props: —

### `src/components/admin/AdminLayout.tsx`

```ts
AdminLayoutProps {
  children: ReactNode;
  title: string;
  subtitle?: string;
  actions?: ReactNode;
}
```

### `src/components/admin/AdminPagination.tsx`

```ts
AdminPaginationProps {
  currentPage: number;
  totalPages: number;
  totalItems: number;
  pageSize: number;
  onPageChange: (page: number) => void;
  onPageSizeChange?: (size: number) => void;
  pageSizeOptions?: number[];
}
```

### `src/components/admin/CompetitionRevenue.tsx`

Props: —

### `src/components/admin/CompetitionSetsRevenue.tsx`

Props: —

### `src/components/admin/CreditHealthDashboard.tsx`

```ts
CreditHealthProps {
  loading: boolean;
  totalOutstanding: number;
  totalIssued: number;
  totalRedeemed: number;
  creditSalesPercent: number;
  monthlyData: { month: string; issued: number; redeemed: number; net: number }[];
}
```

### `src/components/admin/ImageUpload.tsx`

```ts
ImageUploadProps {
  value: string;
  onChange: (url: string) => void;
  bucket?: string;
}
```

### `src/components/admin/InstantWinPrizesEditor.tsx`

```ts
InstantWinPrizesEditorProps {
  competitionId: string | undefined;
  isInstantWin: boolean;
}
```

### `src/components/admin/PlayerBoardEditor.tsx`

```ts
PlayerBoardEditorProps {
  competitionId: string | undefined;
  isPlayerBoard: boolean;
}
```

### `src/components/admin/UserEntriesPanel.tsx`

```ts
UserEntriesPanelProps {
  userId: string;
  userName: string;
  userEmail: string;
  userPhone: string | null;
  userAddress: string;
}
```

### `src/components/admin/ViewAsUserDialog.tsx`

```ts
ViewAsUserDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  userId: string;
  userName: string;
  userEmail: string;
}
```

### `src/components/admin/dashboard/AlertCard.tsx`

```ts
AlertCardProps {
  title: string;
  count: number | string;
  description: string;
  icon: LucideIcon;
  severity: AlertSeverity;
  href?: string;
  loading?: boolean;
}
```

### `src/components/admin/dashboard/ComingSoonCard.tsx`

```ts
Props {
  title: string;
  description: string;
  icon: LucideIcon;
}
```

### `src/components/admin/dashboard/CompetitionHealth.tsx`

Props: —

### `src/components/admin/draw/TicketRoller.tsx`

```ts
TicketRollerProps {
  /** The winning ticket number the roller settles on. */
  value: number;
  /** Fired once every digit has locked. */
  onComplete?: () => void;
  /** Milliseconds between each digit locking. */
  stagger?: number;
  /** Milliseconds before the first digit locks. */
  initialSpin?: number;
}
```

```ts
DigitColumnProps {
  digit: string;
  locked: boolean;
  index: number;
}
```

### `src/components/admin/draw/WinnerReveal.tsx`

```ts
WinnerRevealProps {
  name: string;
  ticketNumber: number;
  type?: 'paid' | 'postal' | string;
  /** Small caption under the card, e.g. the competition title. */
  caption?: string;
}
```

### `src/components/admin/finance/FinanceSubnav.tsx`

Props: —

### `src/components/admin/finance/SectionHeader.tsx`

```ts
Props {
  title: string;
  description?: string;
  icon?: LucideIcon;
  right?: React.ReactNode;
}
```

### `src/components/admin/operations/OperationsStats.tsx`

Props: —

### `src/components/admin/operations/OperationsSubnav.tsx`

Props: —

### `src/components/admin/ui/EmptyState.tsx`

```ts
EmptyStateProps {
  title?: string;
  message?: ReactNode;
  icon?: ReactNode;
  action?: ReactNode;
  className?: string;
  /** Render without a wrapping Card (useful inside table cells). */
  bare?: boolean;
}
```

### `src/components/admin/ui/ErrorState.tsx`

```ts
ErrorStateProps {
  title?: string;
  message?: ReactNode;
  onRetry?: () => void;
  retryLabel?: string;
  className?: string;
  bare?: boolean;
}
```

### `src/components/admin/ui/KpiCard.tsx`

```ts
KpiCardProps {
  label: string;
  value: ReactNode;
  icon?: ReactNode;
  hint?: ReactNode;
  className?: string;
  valueClassName?: string;
}
```

### `src/components/admin/ui/LoadingState.tsx`

```ts
LoadingStateProps {
  message?: string;
  className?: string;
  bare?: boolean;
}
```

### `src/components/admin/ui/SectionHeader.tsx`

```ts
SectionHeaderProps {
  title: string;
  description?: ReactNode;
  icon?: ReactNode;
  actions?: ReactNode;
  className?: string;
}
```

### `src/components/admin/users/UserActions.tsx`

```ts
UserActionsProps {
  email: string;
  userId: string;
  isAdmin: boolean;
  onView: () => void;
  onToggleAdmin: () => void;
  onDelete: () => void;
  /** Mobile layout stacks buttons full-width. */
  layout?: 'inline' | 'stacked';
}
```

### `src/components/admin/users/UserFilters.tsx`

```ts
UserFiltersProps {
  totalCount: number;
  onExport: (optInOnly: boolean) => void;
}
```

### `src/components/admin/users/UserOverviewCards.tsx`

Props: —

### `src/components/admin/users/UserProfileHeader.tsx`

```ts
Props {
  profile: any;
  isAdmin: boolean;
  selfExcludedUntil?: string | null;
  coolOffUntil?: string | null;
}
```

### `src/components/admin/users/UserRow.tsx`

```ts
UserRowProps {
  user: AdminUserProfile;
  expanded: boolean;
  onRowClick: () => void;
  onView: () => void;
  onToggleAdmin: () => void;
  onDelete: () => void;
}
```

### `src/components/admin/users/UserSearchBar.tsx`

```ts
UserSearchBarProps {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
}
```

### `src/components/admin/users/UserTable.tsx`

```ts
UserTableProps {
  users: AdminUserProfile[];
  loading: boolean;
  expandedId: string | null;
  onRowClick: (user: AdminUserProfile) => void;
  onView: (user: AdminUserProfile) => void;
  onToggleAdmin: (user: AdminUserProfile) => void;
  onDelete: (user: AdminUserProfile) => void;
}
```

### `src/components/cart/CartSheet.tsx`

Props: —

### `src/components/competition/CompetitionCard.tsx`

```ts
CompetitionCardProps {
  competition: Competition;
  index?: number;
}
```

### `src/components/competition/CountdownTimer.tsx`

```ts
CountdownTimerProps {
  targetDate: Date | string;
  compact?: boolean;
  minimal?: boolean;
  bar?: boolean;
}
```

### `src/components/competition/EntryPurchaseDialog.tsx`

```ts
EntryPurchaseDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  competitionId: string;
  competitionTitle: string;
  ticketCount: number;
  ticketPrice: number;
  isFree: boolean;
}
```

### `src/components/competition/FreePostalEntryButton.tsx`

Props: —

### `src/components/competition/InstantWinReveal.tsx`

```ts
InstantWinRevealProps {
  open: boolean;
  onClose: () => void;
  result: InstantWinResult | null;
  processing: boolean;
}
```

### `src/components/competition/PlayerBoard.tsx`

```ts
PlayerBoardProps {
  competition: Competition;
}
```

### `src/components/home/BrandsStrip.tsx`

Props: —

### `src/components/home/EmailSignup.tsx`

Props: —

### `src/components/home/FeaturedCompetitions.tsx`

```ts
FeaturedCompetitionsProps {
  competitions: Competition[];
  loading: boolean;
}
```

### `src/components/home/HeroSection.tsx`

```ts
HeroSectionProps {
  competitions: Competition[];
  loading: boolean;
}
```

### `src/components/home/HowItWorksSection.tsx`

Props: —

### `src/components/home/JoinCommunity.tsx`

Props: —

### `src/components/home/LiveActivityToast.tsx`

Props: —

### `src/components/home/PlatformStats.tsx`

Props: —

### `src/components/home/WhyVaultNine.tsx`

Props: —

### `src/components/home/WinnersPreview.tsx`

Props: —

### `src/components/instant-win/InstantWinReveal.tsx`

Props: —

### `src/components/instant-win/brand.tsx`

Props: —

### `src/components/instant-win/games/DartboardReveal.tsx`

Props: —

### `src/components/instant-win/games/WheelReveal.tsx`

Props: —

### `src/components/instant-win/reveal-parts.tsx`

Props: —

### `src/components/instant-win/reveal-result.tsx`

Props: —

### `src/components/instant-win/venue.tsx`

Props: —

### `src/components/layout/AnnouncementBar.tsx`

Props: —

### `src/components/layout/Footer.tsx`

Props: —

### `src/components/layout/Layout.tsx`

```ts
LayoutProps {
  children: ReactNode;
}
```

### `src/components/layout/MobileStickyCTA.tsx`

Props: —

### `src/components/layout/Navigation.tsx`

Props: —

### `src/components/trustpilot/TrustpilotCollectWidget.tsx`

```ts
Props {
  className?: string;
}
```

### `src/components/winner/WinnerVerification.tsx`

Props: —

## shadcn/ui primitives

`src/components/ui/*` are stock shadcn/ui (Radix-based) components: accordion, alert, alert-dialog, aspect-ratio, avatar, badge, breadcrumb, button, calendar, card, carousel, chart, checkbox, collapsible, command, context-menu, dialog, drawer, dropdown-menu, form, hover-card, input, input-otp, label, menubar, navigation-menu, pagination, popover, progress, radio-group, resizable, scroll-area, select, separator, sheet, sidebar, skeleton, slider, sonner, switch, table, tabs, textarea, toast, toaster, toggle, toggle-group, tooltip. Re-implement with your Astro UI layer or keep as React islands.

## Animations & effects

| Effect | Where | How |
|---|---|---|
| Countdown timers | `src/components/competition/CountdownTimer.tsx` | Client `setInterval` against `draw_date` (UTC, displayed UK time via `src/lib/datetime.ts`). Hidden/replaced for `draws_on_sellout` comps ("Draw when sold out"). |
| Instant-win reveal (Classic) | `src/components/instant-win/InstantWinReveal.tsx`, `reveal-flow.ts`, `reveal-parts.tsx`, `reveal-result.tsx`, `reveal-sound.ts` | ~2s suspense then win / no-win card, one ticket at a time, then summary. Sound via WebAudio. |
| Instant-win games: Dartboard, Spin the Wheel | `src/components/instant-win/games/*`, `registry.ts`, `arcade.ts`, `venue.tsx` | Per-competition `instant_win_game` (`classic`/`dartboard`/`wheel`). Outcome is pre-decided server-side; the game only animates to the known result (`adapt.ts` maps result → animation target). |
| Live draw ticket roller & winner reveal | `src/components/admin/draw/*` (TicketRoller, WinnerReveal), `src/pages/admin/AdminLiveDraw.tsx` | Presentation Mode for Facebook Live; framer-motion rolling digits. |
| Scratch card | `src/pages/ScratchTest.tsx` (hidden `/scratch-test`) | Canvas scratch-off prototype; not wired to real prizes. |
| Live activity toasts | `src/components/home/LiveActivityToast.tsx` | Social-proof toasts of recent entries. |
| Winners carousel | `src/components/home/WinnersPreview.tsx` | Embla carousel; trophy fallback card when no photo. |
| Progress bars / sold % | `CompetitionCard.tsx`, `CompetitionDetail.tsx` | Percentage sold display (not remaining count) and instant-win prize "Vault Cards" progress. |
| General motion | framer-motion, `tailwindcss-animate` keyframes (see config) | Accordions, fades, glows. |
