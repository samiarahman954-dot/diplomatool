import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Download, ShoppingCart, Star, Package, AlertCircle } from "lucide-react";
import { DiplomaData } from "./DiplomaBuilder";
import { toast } from "sonner";

interface ActionsPanelProps {
  diplomaData: DiplomaData;
}

export const ActionsPanel = ({ diplomaData }: ActionsPanelProps) => {
  const [addPhysicalExtras, setAddPhysicalExtras] = useState(false);

  const handleDownloadPreview = () => {
    if (!diplomaData.noveltyConfirmation) {
      toast.error("Please confirm this is for novelty use only before downloading");
      return;
    }
    
    if (!diplomaData.fullName || !diplomaData.schoolName || !diplomaData.degree) {
      toast.error("Please fill in all required fields (Name, School, Degree)");
      return;
    }

    toast.success("Watermarked preview will download shortly");
    // Here you would implement the actual download logic
  };

  const handleAddToCart = () => {
    if (!diplomaData.noveltyConfirmation) {
      toast.error("Please confirm this is for novelty use only before adding to cart");
      return;
    }
    
    if (!diplomaData.fullName || !diplomaData.schoolName || !diplomaData.degree) {
      toast.error("Please fill in all required fields (Name, School, Degree)");
      return;
    }

    toast.success("Diploma added to cart! Redirecting to checkout...");
    // Here you would implement WooCommerce integration
  };

  const basePrice = 29.99;
  const extrasPrice = 15.99;
  const totalPrice = basePrice + (addPhysicalExtras ? extrasPrice : 0);

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
      {/* Download Section */}
      <Card className="card-elegant">
        <CardContent className="p-6">
          <div className="flex items-center gap-3 mb-4">
            <div className="p-2 bg-muted rounded-lg">
              <Download className="w-5 h-5 text-primary" />
            </div>
            <div>
              <h3 className="font-semibold text-lg">Download Preview</h3>
              <p className="text-sm text-muted-foreground">Watermarked version for review</p>
            </div>
          </div>
          
          <Button 
            onClick={handleDownloadPreview}
            variant="outline" 
            className="w-full"
            disabled={!diplomaData.noveltyConfirmation || !diplomaData.fullName}
          >
            <Download className="w-4 h-4 mr-2" />
            Download Watermarked Preview
          </Button>
          
          <p className="text-xs text-muted-foreground mt-2 text-center">
            Free watermarked version for review purposes
          </p>
        </CardContent>
      </Card>

      {/* Purchase Section */}
      <Card className="card-elegant border-gold/30">
        <CardContent className="p-6">
          <div className="flex items-center gap-3 mb-4">
            <div className="p-2 bg-gold/10 rounded-lg">
              <Star className="w-5 h-5 text-gold" />
            </div>
            <div>
              <h3 className="font-semibold text-lg">Purchase Diploma</h3>
              <p className="text-sm text-muted-foreground">High-quality, watermark-free version</p>
            </div>
          </div>

          {/* Pricing */}
          <div className="bg-gradient-to-r from-accent to-accent/50 rounded-lg p-4 mb-4">
            <div className="flex justify-between items-center mb-2">
              <span className="font-medium">Digital Diploma (PDF)</span>
              <span className="font-bold text-lg">${basePrice}</span>
            </div>
            
            <div className="flex items-center space-x-2 mb-3">
              <Checkbox
                id="physicalExtras"
                checked={addPhysicalExtras}
                onCheckedChange={(checked) => setAddPhysicalExtras(!!checked)}
              />
              <Label htmlFor="physicalExtras" className="text-sm">
                Add physical seal & premium paper (+${extrasPrice})
              </Label>
            </div>

            <div className="border-t pt-2 flex justify-between items-center">
              <span className="font-bold">Total:</span>
              <span className="font-bold text-xl text-primary">${totalPrice.toFixed(2)}</span>
            </div>
          </div>

          <Button 
            onClick={handleAddToCart}
            className="w-full btn-academic mb-3"
            disabled={!diplomaData.noveltyConfirmation || !diplomaData.fullName}
          >
            <ShoppingCart className="w-4 h-4 mr-2" />
            Add to Cart
          </Button>

          {addPhysicalExtras && (
            <div className="flex items-start gap-2 p-3 bg-blue-50 rounded-lg border border-blue-200">
              <Package className="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" />
              <div className="text-xs text-blue-700">
                <p className="font-medium mb-1">Physical extras include:</p>
                <ul className="space-y-0.5">
                  <li>• Embossed seal sticker</li>
                  <li>• Premium parchment paper</li>
                  <li>• Protective folder</li>
                  <li>• 5-7 business days shipping</li>
                </ul>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Legal Notice */}
      <Card className="md:col-span-2 bg-amber-50 border-amber-200">
        <CardContent className="p-4">
          <div className="flex items-start gap-3">
            <AlertCircle className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
            <div className="text-sm text-amber-800">
              <p className="font-medium mb-1">Important Legal Notice</p>
              <p>
                These diplomas are created for novelty, gift, display, or entertainment purposes only. 
                They are not official educational documents and should not be used to misrepresent 
                educational achievements. By purchasing, you agree to use this product responsibly 
                and in accordance with all applicable laws.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
};