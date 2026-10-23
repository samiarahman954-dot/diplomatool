// Free stamps every sender can use. Later: district stamps, collectibles, a stamp shop.
export interface StampDesign {
  id: string;
  icon: string;
  bn: string;
  en: string;
  color: string;
}

export const STAMPS: StampDesign[] = [
  { id: "shapla", icon: "🪷", bn: "শাপলা", en: "Water lily", color: "#c2185b" },
  { id: "ilish", icon: "🐟", bn: "ইলিশ", en: "Hilsa", color: "#1565c0" },
  { id: "bagh", icon: "🐅", bn: "রয়েল বেঙ্গল", en: "Royal Bengal", color: "#e65100" },
  { id: "nouka", icon: "⛵", bn: "নৌকা", en: "Boat", color: "#00838f" },
  { id: "aam", icon: "🥭", bn: "আম", en: "Mango", color: "#558b2f" },
  { id: "dhan", icon: "🌾", bn: "ধানক্ষেত", en: "Paddy", color: "#9e7c0c" },
  { id: "cha", icon: "🍵", bn: "চা বাগান", en: "Tea garden", color: "#2e7d32" },
  { id: "ektara", icon: "🪕", bn: "একতারা", en: "Ektara", color: "#6a1b9a" },
];

export function stampById(id: string): StampDesign {
  return STAMPS.find((s) => s.id === id) ?? STAMPS[0];
}
