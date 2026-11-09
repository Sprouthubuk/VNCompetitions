// Sample data taken from the live site on 8 Nov 2026.
// In production this comes from the Laravel API (GET /api/competitions).

export type PrizeKind = 'darts' | 'board' | 'ring' | 'box' | 'voucher';
export type Category = 'Instant win' | 'Darts' | 'Boards' | 'Mystery boxes' | 'Vouchers';

export interface InstantPrize {
  name: string;
  left: number;
  total: number;
  tier: 'Premium' | 'Darts' | 'Accessories' | 'Credit';
}

export interface Bundle { tickets: number; price: number; tag?: string; }

export interface Comp {
  slug: string;
  title: string;
  brand: string;
  kind: PrizeKind;
  type: 'standard' | 'instant';
  category: Category;
  price: number;
  was?: number;
  sold: number;
  max: number;
  drawAt: string; // ISO, UTC
  drawMode: 'live' | 'auto';
  description: string;
  cashAlternative?: number;
  bundles?: Bundle[];
  buyX?: { buy: number; free: number };
  multiplier?: { from: number; times: number };
  endPrize?: string;
  instantPrizes?: InstantPrize[];
  game?: 'wheel' | 'dartboard' | 'classic';
  instantCount?: number; // total instant prizes
  instantRemaining?: number;
  amount?: string; // voucher face value for artwork
  featured?: boolean; // pinned to the home page hero carousel
}

const WED = '2026-11-11T21:30:00Z';

export const comps: Comp[] = [
  {
    slug: 'target-takeover',
    featured: true,
    title: 'Target Takeover',
    brand: 'Target',
    kind: 'ring',
    type: 'instant',
    category: 'Instant win',
    price: 1,
    sold: 3973,
    max: 29999,
    drawAt: '2026-11-30T21:00:00Z',
    drawMode: 'auto',
    game: 'wheel',
    instantCount: 29999,
    instantRemaining: 26026,
    description: '29,999 prizes, from a full Target Omni home set-up down to site credit. Every ticket wins something.',
    endPrize: '[End prize name]',
    multiplier: { from: 50, times: 2 },
    instantPrizes: [
      { name: 'Target Omni Ultimate Home Set-Up', left: 1, total: 1, tier: 'Premium' },
      { name: 'Target Omni Auto Scoring System', left: 3, total: 3, tier: 'Premium' },
      { name: 'Target Shift SP 90% (toolbox edition)', left: 3, total: 3, tier: 'Darts' },
      { name: 'Phil Taylor Power Chrono SP 95%', left: 2, total: 3, tier: 'Darts' },
      { name: 'Raymond van Barneveld 95K SP 95%', left: 2, total: 3, tier: 'Darts' },
      { name: 'Luke Littler Fallout SP 90%', left: 6, total: 6, tier: 'Darts' },
      { name: 'Chris Dobey 95K SP 95%', left: 3, total: 3, tier: 'Darts' },
      { name: 'Stephen Bunting G5 Void SP 95%', left: 3, total: 3, tier: 'Darts' },
      { name: 'Target Corona Vision 2 light', left: 2, total: 3, tier: 'Accessories' },
      { name: 'Target TOR EVO dartboard', left: 4, total: 6, tier: 'Accessories' },
      { name: 'Littler World Champion 2026 BOA case', left: 5, total: 6, tier: 'Accessories' },
      { name: 'Target Pro.Ultra flights ×3', left: 54, total: 60, tier: 'Accessories' },
      { name: '50p site credit', left: 1820, total: 2100, tier: 'Credit' },
      { name: '25p site credit', left: 3116, total: 3600, tier: 'Credit' },
      { name: '20p site credit', left: 7787, total: 9000, tier: 'Credit' },
      { name: '10p site credit', left: 13088, total: 15046, tier: 'Credit' },
    ],
  },
  {
    slug: 'world-championship-countdown',
    featured: true,
    title: 'World Championship Countdown',
    brand: 'Countdown',
    kind: 'darts',
    type: 'instant',
    category: 'Instant win',
    price: 1,
    sold: 912,
    max: 99999,
    drawAt: '2026-12-11T21:00:00Z',
    drawMode: 'auto',
    game: 'dartboard',
    instantCount: 3308,
    instantRemaining: 3308,
    description: '3,308 instant prizes including a Target Omni home set-up, Winmau Blade X boards and pro darts.',
    instantPrizes: [
      { name: 'Target Omni Ultimate Home Set-Up', left: 1, total: 1, tier: 'Premium' },
      { name: 'Target Omni Auto Scoring System', left: 5, total: 5, tier: 'Premium' },
      { name: 'Target Shift SP 90% (toolbox edition)', left: 10, total: 10, tier: 'Darts' },
      { name: 'Winmau Blade X official PDC dartboard', left: 10, total: 10, tier: 'Accessories' },
      { name: 'Red Dragon Luke Humphries TX1 Pioneer', left: 5, total: 5, tier: 'Darts' },
      { name: 'Winmau MvG Signature Edition', left: 5, total: 5, tier: 'Darts' },
      { name: 'Pro.Ultra player flights ×3', left: 200, total: 200, tier: 'Accessories' },
      { name: '£1 Vault Nine site credit', left: 988, total: 1000, tier: 'Credit' },
      { name: '50p Vault Nine site credit', left: 1487, total: 1500, tier: 'Credit' },
    ],
  },
  {
    slug: 'pick-your-board',
    featured: true,
    title: 'Pick your board',
    brand: 'Unicorn',
    kind: 'board',
    type: 'standard',
    category: 'Boards',
    price: 0.29,
    was: 0.49,
    sold: 18,
    max: 999,
    drawAt: WED,
    drawMode: 'live',
    description: 'Winner chooses a Unicorn 2.0, Target Tor, Samurai Graphite Edition or Harrows Fortis board.',
    bundles: [{ tickets: 10, price: 2.5 }, { tickets: 25, price: 5.75, tag: 'Popular' }, { tickets: 50, price: 10, tag: 'Best value' }],
    buyX: { buy: 10, free: 2 },
  },
  {
    slug: 'pick-your-sables',
    title: 'Pick your sables',
    brand: 'Target',
    kind: 'darts',
    type: 'standard',
    category: 'Darts',
    price: 0.25,
    was: 0.39,
    sold: 15,
    max: 999,
    drawAt: WED,
    drawMode: 'live',
    description: 'Any weight and barrel shape. Winner picks the set.',
    buyX: { buy: 10, free: 2 },
  },
  {
    slug: 'winmau-mystery-box',
    featured: true,
    title: '£150 Winmau mystery box',
    brand: 'Winmau',
    kind: 'box',
    type: 'standard',
    category: 'Mystery boxes',
    price: 0.99,
    was: 1.99,
    sold: 5,
    max: 399,
    drawAt: WED,
    drawMode: 'live',
    description: 'A new-launch Winmau mystery box worth £150.',
  },
  {
    slug: 'shot-mystery-box',
    title: '£150 Shot mystery box',
    brand: 'Shot',
    kind: 'box',
    type: 'standard',
    category: 'Mystery boxes',
    price: 1.79,
    was: 1.99,
    sold: 10,
    max: 399,
    drawAt: WED,
    drawMode: 'live',
    description: 'A new-launch Shot mystery box worth £150.',
  },
  {
    slug: 'red-dragon-mystery-box',
    title: '£150 Red Dragon mystery box',
    brand: 'Red Dragon',
    kind: 'box',
    type: 'standard',
    category: 'Mystery boxes',
    price: 1.79,
    was: 1.99,
    sold: 0,
    max: 399,
    drawAt: WED,
    drawMode: 'auto',
    description: 'A new-launch Red Dragon mystery box worth £150.',
  },
  {
    slug: 'dorset-darts-voucher',
    title: '£100 Dorset Darts Shop voucher',
    brand: 'Dorset Darts',
    kind: 'voucher',
    amount: '£100',
    type: 'standard',
    category: 'Vouchers',
    price: 1.99,
    was: 2.49,
    sold: 0,
    max: 399,
    drawAt: WED,
    drawMode: 'live',
    description: '£100 to spend online at the Dorset Darts Shop.',
  },
];

export const categories: Category[] = ['Instant win', 'Darts', 'Boards', 'Mystery boxes', 'Vouchers'];

export const winners = [
  { name: 'Craig Baker', prize: 'Star Wars Target Mandalorian 26g', ticket: '#143', date: '08 Nov 2026' },
  { name: 'Toby Shears', prize: '£150 Harrows mystery box', ticket: '#9', date: '08 Nov 2026' },
  { name: 'McKenzie Vetier', prize: 'Ryan Searle GEO darts 32g', ticket: '#171', date: '08 Nov 2026' },
  { name: 'Jamie Guest', prize: 'Target Rove darts 23g', ticket: '#126', date: '08 Nov 2026' },
  { name: 'Ben Carter', prize: 'Luke Littler Nuke Fallout 24g', ticket: 'live draw', date: '' },
  { name: 'Leigh Picton', prize: 'Phil Taylor Power Chrono 26g', ticket: 'live draw', date: '' },
  { name: 'Dean Bush', prize: 'Charlie Manby 25g 95%', ticket: 'live draw', date: '' },
  { name: 'Robert Pickford', prize: 'Littler Born To Win dartboard', ticket: 'live draw', date: '' },
];

export const money = (v: number) => (v < 1 ? `${Math.round(v * 100)}p` : `£${v % 1 === 0 ? v : v.toFixed(2)}`);
export const gbp = (v: number) => `£${v.toFixed(2)}`;
export const pct = (c: Comp) => Math.round((c.sold / c.max) * 100);
export const fmt = (n: number) => n.toLocaleString('en-GB');
export const instantLeft = (c: Comp) => (c.instantPrizes ?? []).reduce((t, p) => t + p.left, 0);
export const instantTotal = (c: Comp) => (c.instantPrizes ?? []).reduce((t, p) => t + p.total, 0);
