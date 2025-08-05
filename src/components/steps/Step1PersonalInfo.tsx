import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { CalendarIcon, Type } from "lucide-react";
import { format } from "date-fns";
import { cn } from "@/lib/utils";
import { DiplomaData } from "../DiplomaBuilder";

interface Step1PersonalInfoProps {
  diplomaData: DiplomaData;
  updateDiplomaData: (updates: Partial<DiplomaData>) => void;
}

export const Step1PersonalInfo = ({ diplomaData, updateDiplomaData }: Step1PersonalInfoProps) => {
  return (
    <div className="space-y-6">
      <div className="space-y-4">
        <h3 className="text-lg font-semibold text-primary flex items-center gap-2">
          <Type className="w-5 h-5" />
          Personal Information
        </h3>
        
        <div className="space-y-2">
          <Label htmlFor="fullName">Full Name *</Label>
          <Input
            id="fullName"
            placeholder="Enter full name as it should appear"
            value={diplomaData.fullName}
            onChange={(e) => updateDiplomaData({ fullName: e.target.value })}
            className="input-elegant"
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="schoolName">School/University Name *</Label>
          <Input
            id="schoolName"
            placeholder="Enter institution name"
            value={diplomaData.schoolName}
            onChange={(e) => updateDiplomaData({ schoolName: e.target.value })}
            className="input-elegant"
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="degree">Degree or Title *</Label>
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

        <div className="text-sm text-muted-foreground">
          * Required fields
        </div>
      </div>
    </div>
  );
};