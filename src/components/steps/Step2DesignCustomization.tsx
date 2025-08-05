import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Palette, Stamp } from "lucide-react";
import { cn } from "@/lib/utils";
import { DiplomaData } from "../DiplomaBuilder";

// Import template and seal images
import diplomaClassic from "@/assets/diploma-template-classic.jpg";
import diplomaModern from "@/assets/diploma-template-modern.jpg";
import diplomaLuxury from "@/assets/diploma-template-luxury.jpg";
import sealClassic from "@/assets/seal-classic.jpg";
import sealModern from "@/assets/seal-modern.jpg";
import sealPremium from "@/assets/seal-premium.jpg";

interface Step2DesignCustomizationProps {
  diplomaData: DiplomaData;
  updateDiplomaData: (updates: Partial<DiplomaData>) => void;
}

const templateOptions = [
  { id: "classic", name: "Classic Traditional", image: diplomaClassic },
  { id: "modern", name: "Modern Minimalist", image: diplomaModern },
  { id: "luxury", name: "Luxury Premium", image: diplomaLuxury },
];

const sealOptions = [
  { id: "classic", name: "Classic Seal", image: sealClassic },
  { id: "modern", name: "Modern Seal", image: sealModern },
  { id: "premium", name: "Premium Seal", image: sealPremium },
];

const fontOptions = [
  { id: "playfair", name: "Playfair Display (Classic)" },
  { id: "inter", name: "Inter (Modern)" },
  { id: "serif", name: "Times New Roman (Traditional)" },
  { id: "sans", name: "Arial (Clean)" },
];

const paperColors = [
  { id: "cream", name: "Cream", color: "#F5F5DC" },
  { id: "white", name: "Pure White", color: "#FFFFFF" },
  { id: "ivory", name: "Ivory", color: "#FFFFF0" },
  { id: "antique", name: "Antique White", color: "#FAEBD7" },
];

export const Step2DesignCustomization = ({ diplomaData, updateDiplomaData }: Step2DesignCustomizationProps) => {
  return (
    <div className="space-y-6">
      <div className="space-y-4">
        <h3 className="text-lg font-semibold text-primary flex items-center gap-2">
          <Palette className="w-5 h-5" />
          Design Customization
        </h3>

        <div className="space-y-2">
          <Label>Font Style</Label>
          <Select value={diplomaData.fontStyle} onValueChange={(value) => updateDiplomaData({ fontStyle: value })}>
            <SelectTrigger className="input-elegant">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {fontOptions.map((font) => (
                <SelectItem key={font.id} value={font.id}>
                  {font.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          <Label>Paper Color</Label>
          <Select value={diplomaData.paperColor} onValueChange={(value) => updateDiplomaData({ paperColor: value })}>
            <SelectTrigger className="input-elegant">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {paperColors.map((color) => (
                <SelectItem key={color.id} value={color.id}>
                  <div className="flex items-center gap-2">
                    <div 
                      className="w-4 h-4 rounded border border-gray-300"
                      style={{ backgroundColor: color.color }}
                    />
                    {color.name}
                  </div>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          <Label>Template Style</Label>
          <div className="grid grid-cols-1 gap-3">
            {templateOptions.map((template) => (
              <div
                key={template.id}
                className={cn(
                  "relative cursor-pointer rounded-lg border-2 transition-all duration-300",
                  diplomaData.templateStyle === template.id
                    ? "border-gold ring-2 ring-gold/30"
                    : "border-border hover:border-primary/50"
                )}
                onClick={() => updateDiplomaData({ templateStyle: template.id })}
              >
                <div className="p-3 flex items-center gap-3">
                  <img
                    src={template.image}
                    alt={template.name}
                    className="w-16 h-12 object-cover rounded"
                  />
                  <span className="font-medium">{template.name}</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="space-y-2">
          <Label className="flex items-center gap-2">
            <Stamp className="w-4 h-4" />
            Seal Type
          </Label>
          <div className="grid grid-cols-1 gap-3">
            {sealOptions.map((seal) => (
              <div
                key={seal.id}
                className={cn(
                  "relative cursor-pointer rounded-lg border-2 transition-all duration-300",
                  diplomaData.sealType === seal.id
                    ? "border-gold ring-2 ring-gold/30"
                    : "border-border hover:border-primary/50"
                )}
                onClick={() => updateDiplomaData({ sealType: seal.id })}
              >
                <div className="p-3 flex items-center gap-3">
                  <img
                    src={seal.image}
                    alt={seal.name}
                    className="w-12 h-12 object-cover rounded-full"
                  />
                  <span className="font-medium">{seal.name}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};