import { DiplomaData } from "./DiplomaBuilder";
import { format } from "date-fns";
import { cn } from "@/lib/utils";

// Import template and seal images
import diplomaClassic from "@/assets/diploma-template-classic.jpg";
import diplomaModern from "@/assets/diploma-template-modern.jpg";
import diplomaLuxury from "@/assets/diploma-template-luxury.jpg";
import sealClassic from "@/assets/seal-classic.jpg";
import sealModern from "@/assets/seal-modern.jpg";
import sealPremium from "@/assets/seal-premium.jpg";

interface DiplomaPreviewProps {
  diplomaData: DiplomaData;
}

const templateImages = {
  classic: diplomaClassic,
  modern: diplomaModern,
  luxury: diplomaLuxury,
};

const sealImages = {
  classic: sealClassic,
  modern: sealModern,
  premium: sealPremium,
};

const paperColorMap = {
  cream: "#F5F5DC",
  white: "#FFFFFF",
  ivory: "#FFFFF0",
  antique: "#FAEBD7",
};

const fontClassMap = {
  playfair: "font-playfair",
  inter: "font-inter",
  serif: "font-serif",
  sans: "font-sans-serif",
};

export const DiplomaPreview = ({ diplomaData }: DiplomaPreviewProps) => {
  const templateImage = templateImages[diplomaData.templateStyle as keyof typeof templateImages];
  const sealImage = sealImages[diplomaData.sealType as keyof typeof sealImages];
  const backgroundColor = paperColorMap[diplomaData.paperColor as keyof typeof paperColorMap];
  const fontClass = fontClassMap[diplomaData.fontStyle as keyof typeof fontClassMap];

  return (
    <div className="relative">
      {/* Preview Container */}
      <div 
        className="relative w-full aspect-[4/3] diploma-preview overflow-hidden"
        style={{ backgroundColor }}
      >
        {/* Background Template */}
        <div 
          className="absolute inset-0 opacity-20 bg-cover bg-center"
          style={{ backgroundImage: `url(${templateImage})` }}
        />

        {/* Watermark */}
        <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
          <div className="text-6xl font-bold text-gray-300 opacity-20 rotate-[-45deg] select-none">
            PREVIEW
          </div>
        </div>

        {/* Content Overlay */}
        <div className="relative z-10 h-full flex flex-col items-center justify-center p-8 text-center">
          
          {/* Header */}
          <div className="mb-6">
            <h1 className={cn("text-3xl md:text-4xl font-bold text-primary mb-2", fontClass)}>
              Certificate of Achievement
            </h1>
            <div className="w-32 h-0.5 bg-gold mx-auto"></div>
          </div>

          {/* School Name */}
          <div className="mb-6">
            <h2 className={cn("text-xl md:text-2xl text-primary/80", fontClass)}>
              {diplomaData.schoolName || "University Name"}
            </h2>
          </div>

          {/* Main Text */}
          <div className="mb-6 max-w-lg">
            <p className={cn("text-sm md:text-base text-foreground/80 leading-relaxed", fontClass)}>
              This is to certify that
            </p>
          </div>

          {/* Student Name */}
          <div className="mb-6">
            <h3 className={cn("text-2xl md:text-3xl font-bold text-primary border-b-2 border-gold pb-2 px-4", fontClass)}>
              {diplomaData.fullName || "Student Name"}
            </h3>
          </div>

          {/* Degree Text */}
          <div className="mb-6 max-w-xl">
            <p className={cn("text-sm md:text-base text-foreground/80 leading-relaxed", fontClass)}>
              has successfully completed the requirements for the degree of
            </p>
          </div>

          {/* Degree Name */}
          <div className="mb-6">
            <h4 className={cn("text-xl md:text-2xl font-semibold text-primary", fontClass)}>
              {diplomaData.degree || "Degree Title"}
            </h4>
          </div>

          {/* Additional Notes */}
          {diplomaData.additionalNotes && (
            <div className="mb-6">
              <p className={cn("text-sm text-foreground/70 italic", fontClass)}>
                {diplomaData.additionalNotes}
              </p>
            </div>
          )}

          {/* Bottom Section */}
          <div className="flex items-center justify-between w-full max-w-lg mt-auto">
            {/* Date */}
            <div className="text-center">
              <div className="border-b border-foreground/30 pb-1 mb-1 min-w-[120px]">
                <span className={cn("text-sm", fontClass)}>
                  {diplomaData.graduationDate ? format(diplomaData.graduationDate, "MMMM d, yyyy") : "Date"}
                </span>
              </div>
              <p className="text-xs text-foreground/60">Date</p>
            </div>

            {/* Seal */}
            <div className="mx-8">
              <img
                src={sealImage}
                alt="University Seal"
                className="w-16 h-16 md:w-20 md:h-20 object-cover rounded-full border-2 border-gold"
              />
            </div>

            {/* Signature Line */}
            <div className="text-center">
              <div className="border-b border-foreground/30 pb-1 mb-1 min-w-[120px]">
                <span className="text-sm invisible">Signature</span>
              </div>
              <p className="text-xs text-foreground/60">Registrar</p>
            </div>
          </div>
        </div>

        {/* Decorative Border */}
        <div className="absolute inset-4 border-4 border-gold/30 rounded-lg pointer-events-none"></div>
        <div className="absolute inset-6 border border-gold/20 rounded-lg pointer-events-none"></div>
      </div>

      {/* Preview Info */}
      <div className="mt-4 text-center">
        <p className="text-sm text-muted-foreground">
          This is a watermarked preview. The final diploma will be delivered without watermarks.
        </p>
      </div>
    </div>
  );
};