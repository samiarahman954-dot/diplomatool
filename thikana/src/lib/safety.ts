// Mailboxes are public, so open letters must not carry contact details that
// would let someone be tracked down. supabase/schema.sql repeats these checks.

export const MAX_BODY = 3000;
export const MAX_PEN_NAME = 40;
export const HIDE_AFTER_REPORTS = 3;

const BN_DIGITS = "০১২৩৪৫৬৭৮৯";

function asciiDigits(s: string): string {
  return s.replace(/[০-৯]/g, (c) => String(BN_DIGITS.indexOf(c)));
}

export type SafetyIssue = "empty" | "too_long" | "phone" | "email" | "link";

export function checkBody(body: string): SafetyIssue | null {
  const text = body.trim();
  if (!text) return "empty";
  if (text.length > MAX_BODY) return "too_long";
  const digits = asciiDigits(text);
  // BD mobile (01XNNNNNNNN, optional +88 and separators), international numbers,
  // and long digit runs such as NID or bank account numbers.
  if (
    /(?:\+?88[\s-]*)?\b01[3-9](?:[\s-]*\d){8}\b/.test(digits) ||
    /\+\d(?:[\s-]*\d){7,}/.test(digits) ||
    /\d{10,}/.test(digits)
  )
    return "phone";
  if (/[^\s@]+@[^\s@]+\.[a-z]{2,}/i.test(text)) return "email";
  if (/https?:\/\/|www\.|\b[a-z0-9-]+\.(com|net|org|xyz|io|me|bd)\b/i.test(text)) return "link";
  return null;
}
