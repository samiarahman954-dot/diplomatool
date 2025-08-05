import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { MultiStepDiplomaForm } from "./MultiStepDiplomaForm";
import { DiplomaPreview } from "./DiplomaPreview";
import { ActionsPanel } from "./ActionsPanel";
import { GraduationCap, Award, Sparkles } from "lucide-react";

export interface DiplomaData {
  fullName: string;
  schoolName: string;
  degree: string;
  graduationDate: Date | undefined;
  additionalNotes: string;
  fontStyle: string;
  paperColor: string;
  sealType: string;
  templateStyle: string;
  noveltyConfirmation: boolean;
}

const DiplomaBuilder = () => {
  const [diplomaData, setDiplomaData] = useState<DiplomaData>({
    fullName: "",
    schoolName: "",
    degree: "",
    graduationDate: undefined,
    additionalNotes: "",
    fontStyle: "playfair",
    paperColor: "cream",
    sealType: "classic",
    templateStyle: "classic",
    noveltyConfirmation: false,
  });

  const updateDiplomaData = (updates: Partial<DiplomaData>) => {
    setDiplomaData(prev => ({ ...prev, ...updates }));
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-background to-accent/30">
      {/* Header */}
      <div className="bg-gradient-to-r from-primary to-primary-hover shadow-[var(--shadow-elegant)]">
        <div className="max-w-7xl mx-auto px-4 py-8">
          <div className="flex items-center gap-4 text-primary-foreground">
            <div className="p-3 bg-white/10 rounded-xl">
              <GraduationCap className="w-8 h-8" />
            </div>
            <div>
              <h1 className="text-4xl font-bold font-playfair">Diploma Builder Tool</h1>
              <p className="text-primary-foreground/80 text-lg">Create custom diplomas for display, gifts, or film props</p>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 py-8">
        <div className="grid grid-cols-1 xl:grid-cols-3 gap-8">
          {/* Form Section */}
          <div className="xl:col-span-1">
            <Card className="card-elegant">
              <CardHeader className="bg-gradient-to-r from-accent to-accent/50">
                <CardTitle className="flex items-center gap-2 text-2xl font-playfair">
                  <Award className="w-6 h-6 text-gold" />
                  Customize Your Diploma
                </CardTitle>
              </CardHeader>
              <CardContent className="p-6">
                <MultiStepDiplomaForm 
                  diplomaData={diplomaData} 
                  updateDiplomaData={updateDiplomaData} 
                />
              </CardContent>
            </Card>
          </div>

          {/* Preview Section */}
          <div className="xl:col-span-2">
            <Card className="card-elegant">
              <CardHeader className="bg-gradient-to-r from-accent to-accent/50">
                <CardTitle className="flex items-center gap-2 text-2xl font-playfair">
                  <Sparkles className="w-6 h-6 text-gold" />
                  Live Preview
                </CardTitle>
              </CardHeader>
              <CardContent className="p-6">
                <DiplomaPreview diplomaData={diplomaData} />
              </CardContent>
            </Card>

            {/* Actions Panel */}
            <div className="mt-6">
              <ActionsPanel diplomaData={diplomaData} />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DiplomaBuilder;