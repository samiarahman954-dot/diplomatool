import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { DiplomaData } from "./DiplomaBuilder";
import { Step1PersonalInfo } from "./steps/Step1PersonalInfo";
import { Step2DesignCustomization } from "./steps/Step2DesignCustomization";
import { Step3Confirmation } from "./steps/Step3Confirmation";

interface MultiStepDiplomaFormProps {
  diplomaData: DiplomaData;
  updateDiplomaData: (updates: Partial<DiplomaData>) => void;
}

const steps = [
  { id: 1, title: "Personal Information", description: "Enter your details" },
  { id: 2, title: "Design Customization", description: "Choose your style" },
  { id: 3, title: "Confirmation", description: "Review and confirm" },
];

export const MultiStepDiplomaForm = ({ diplomaData, updateDiplomaData }: MultiStepDiplomaFormProps) => {
  const [currentStep, setCurrentStep] = useState(1);

  const nextStep = () => {
    if (currentStep < steps.length) {
      setCurrentStep(currentStep + 1);
    }
  };

  const prevStep = () => {
    if (currentStep > 1) {
      setCurrentStep(currentStep - 1);
    }
  };

  const getCurrentStepComponent = () => {
    switch (currentStep) {
      case 1:
        return <Step1PersonalInfo diplomaData={diplomaData} updateDiplomaData={updateDiplomaData} />;
      case 2:
        return <Step2DesignCustomization diplomaData={diplomaData} updateDiplomaData={updateDiplomaData} />;
      case 3:
        return <Step3Confirmation diplomaData={diplomaData} updateDiplomaData={updateDiplomaData} />;
      default:
        return <Step1PersonalInfo diplomaData={diplomaData} updateDiplomaData={updateDiplomaData} />;
    }
  };

  const canProceed = () => {
    switch (currentStep) {
      case 1:
        return diplomaData.fullName && diplomaData.schoolName && diplomaData.degree;
      case 2:
        return true; // Design options have defaults
      case 3:
        return diplomaData.noveltyConfirmation;
      default:
        return false;
    }
  };

  const progress = (currentStep / steps.length) * 100;

  return (
    <div className="space-y-6">
      {/* Step Progress */}
      <div className="space-y-4">
        <div className="flex justify-between items-center">
          <h3 className="text-lg font-semibold text-primary">
            Step {currentStep} of {steps.length}
          </h3>
          <span className="text-sm text-muted-foreground">
            {steps[currentStep - 1]?.title}
          </span>
        </div>
        
        <Progress value={progress} className="h-2" />
        
        <p className="text-sm text-muted-foreground">
          {steps[currentStep - 1]?.description}
        </p>
      </div>

      {/* Step Content */}
      <div className="min-h-[400px]">
        {getCurrentStepComponent()}
      </div>

      {/* Navigation Buttons */}
      <div className="flex justify-between pt-6 border-t border-border">
        <Button
          variant="outline"
          onClick={prevStep}
          disabled={currentStep === 1}
          className="flex items-center gap-2"
        >
          <ChevronLeft className="w-4 h-4" />
          Previous
        </Button>

        <Button
          onClick={nextStep}
          disabled={currentStep === steps.length || !canProceed()}
          className="flex items-center gap-2"
        >
          {currentStep === steps.length ? "Complete" : "Next"}
          {currentStep < steps.length && <ChevronRight className="w-4 h-4" />}
        </Button>
      </div>
    </div>
  );
};