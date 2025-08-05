import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import { CheckCircle, User, GraduationCap, Calendar, Palette } from "lucide-react";
import { format } from "date-fns";
import { DiplomaData } from "../DiplomaBuilder";

interface Step3ConfirmationProps {
  diplomaData: DiplomaData;
  updateDiplomaData: (updates: Partial<DiplomaData>) => void;
}

export const Step3Confirmation = ({ diplomaData, updateDiplomaData }: Step3ConfirmationProps) => {
  return (
    <div className="space-y-6">
      <div className="space-y-4">
        <h3 className="text-lg font-semibold text-primary flex items-center gap-2">
          <CheckCircle className="w-5 h-5" />
          Review Your Diploma
        </h3>

        {/* Summary Cards */}
        <div className="grid gap-4">
          {/* Personal Information Summary */}
          <Card>
            <CardContent className="p-4">
              <h4 className="font-semibold flex items-center gap-2 mb-3">
                <User className="w-4 h-4" />
                Personal Information
              </h4>
              <div className="space-y-2 text-sm">
                <div>
                  <span className="font-medium">Name:</span> {diplomaData.fullName || "Not provided"}
                </div>
                <div>
                  <span className="font-medium">School:</span> {diplomaData.schoolName || "Not provided"}
                </div>
                <div>
                  <span className="font-medium">Degree:</span> {diplomaData.degree || "Not provided"}
                </div>
                <div>
                  <span className="font-medium">Date:</span> {
                    diplomaData.graduationDate 
                      ? format(diplomaData.graduationDate, "MMMM d, yyyy")
                      : "Not selected"
                  }
                </div>
                {diplomaData.additionalNotes && (
                  <div>
                    <span className="font-medium">Notes:</span> {diplomaData.additionalNotes}
                  </div>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Design Summary */}
          <Card>
            <CardContent className="p-4">
              <h4 className="font-semibold flex items-center gap-2 mb-3">
                <Palette className="w-4 h-4" />
                Design Options
              </h4>
              <div className="space-y-2 text-sm">
                <div>
                  <span className="font-medium">Template:</span> {diplomaData.templateStyle}
                </div>
                <div>
                  <span className="font-medium">Font:</span> {diplomaData.fontStyle}
                </div>
                <div>
                  <span className="font-medium">Paper:</span> {diplomaData.paperColor}
                </div>
                <div>
                  <span className="font-medium">Seal:</span> {diplomaData.sealType}
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Confirmation Checkbox */}
        <div className="space-y-4 pt-4 border-t border-border">
          <div className="flex items-start space-x-3">
            <Checkbox
              id="noveltyConfirmation"
              checked={diplomaData.noveltyConfirmation}
              onCheckedChange={(checked) => updateDiplomaData({ noveltyConfirmation: !!checked })}
              className="mt-1"
            />
            <div className="space-y-1">
              <Label htmlFor="noveltyConfirmation" className="text-sm font-medium leading-relaxed">
                I confirm this is for novelty/gift/display use only
              </Label>
              <p className="text-xs text-muted-foreground">
                This diploma is not intended to misrepresent educational achievements and is for entertainment, 
                display, or gift purposes only.
              </p>
            </div>
          </div>
        </div>

        {!diplomaData.noveltyConfirmation && (
          <div className="text-sm text-amber-600 bg-amber-50 p-3 rounded-lg border border-amber-200">
            Please confirm the intended use to proceed with your order.
          </div>
        )}
      </div>
    </div>
  );
};