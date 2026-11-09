// One place that knows how internal links are written.
// Preview builds are flat .html files with relative links; production uses clean URLs.
const preview = import.meta.env.PUBLIC_PREVIEW === '1';

export function href(page: string): string {
  if (page === '' || page === 'index') return preview ? 'index.html' : '/';
  return preview ? `${page}.html` : `/${page}`;
}

export function compHref(slug: string): string {
  return href(`comp-${slug}`);
}

export function asset(path: string): string {
  return preview ? path : `/${path}`;
}
