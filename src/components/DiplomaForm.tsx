import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { CalendarIcon, Palette, Type, Stamp } from "lucide-react";
import { format } from "date-fns";
import { cn } from "@/lib/utils";
import { DiplomaData } from "./DiplomaBuilder";

// Import template and seal images
import diplomaClassic from "@/assets/diploma-template-classic.jpg";
import diplomaModern from "@/assets/diploma-template-modern.jpg";
import diplomaLuxury from "@/assets/diploma-template-luxury.jpg";
import sealClassic from "@/assets/seal-classic.jpg";
import sealModern from "@/assets/seal-modern.jpg";
import sealPremium from "@/assets/seal-premium.jpg";

interface DiplomaFormProps {
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

export const DiplomaForm = ({ diplomaData, updateDiplomaData }: DiplomaFormProps) => {
  return (
    <div className="space-y-6">
      {/* Personal Information */}
      <div className="space-y-4">
        <h3 className="text-lg font-semibold text-primary flex items-center gap-2">
          <Type className="w-5 h-5" />
          Personal Information
        </h3>
        
        <div className="space-y-2">
          <Label htmlFor="fullName">Full Name</Label>
          <Input
            id="fullName"
            placeholder="Enter full name as it should appear"
            value={diplomaData.fullName}
            onChange={(e) => updateDiplomaData({ fullName: e.target.value })}
            className="input-elegant"
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="schoolName">School/University Name</Label>
          <Input
            id="schoolName"
            placeholder="Enter institution name"
            value={diplomaData.schoolName}
            onChange={(e) => updateDiplomaData({ schoolName: e.target.value })}
            className="input-elegant"
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="degree">Degree or Title</Label>
          <Input
            id="degree"
            placeholder="e.g., Bachelor of Arts, Master of Science"
            value={diplomaData.degree}
            onChange={(e) => updateDiplomaData({ degree: e.target.value })}
            className="input-elegant"
          />
        </div>

        <div className="space-y-2">
          <Label>Graduation Date</Label>
          <Popover>
            <PopoverTrigger asChild>
              <Button
                variant="outline"
                className={cn(
                  "w-full justify-start text-left font-normal input-elegant",
                  !diplomaData.graduationDate && "text-muted-foreground"
                )}
              >
                <CalendarIcon className="mr-2 h-4 w-4" />
                {diplomaData.graduationDate ? (
                  format(diplomaData.graduationDate, "PPP")
                ) : (
                  <span>Pick a date</span>
                )}
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
              <Calendar
                mode="single"
                selected={diplomaData.graduationDate}
                onSelect={(date) => updateDiplomaData({ graduationDate: date })}
                initialFocus
                className="p-3 pointer-events-auto"
              />
            </PopoverContent>
          </Popover>
        </div>

        <div className="space-y-2">
          <Label htmlFor="additionalNotes">Additional Notes (Optional)</Label>
          <Textarea
            id="additionalNotes"
            placeholder="Special honors, Latin honors, etc."
            value={diplomaData.additionalNotes}
            onChange={(e) => updateDiplomaData({ additionalNotes: e.target.value })}
            className="input-elegant min-h-[80px]"
          />
        </div>
      </div>

      {/* Design Customization */}
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

      {/* Confirmation */}
      <div className="space-y-4">
        <div className="flex items-center space-x-2">
          <Checkbox
            id="noveltyConfirmation"
            checked={diplomaData.noveltyConfirmation}
            onCheckedChange={(checked) => updateDiplomaData({ noveltyConfirmation: !!checked })}
          />
          <Label htmlFor="noveltyConfirmation" className="text-sm leading-relaxed">
            I confirm this is for novelty/gift/display use only and not intended to misrepresent educational achievements.
          </Label>
        </div>
      </div>
    </div>
  );
};