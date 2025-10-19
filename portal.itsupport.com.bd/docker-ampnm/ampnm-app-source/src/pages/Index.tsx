import { BrowserRouter, Routes, Route } from "react-router-dom";
import MainApp from "./MainApp";
import NotFound from "./NotFound";
import Products from "./Products";
import Maintenance from "./Maintenance"; // Import Maintenance page

const Index = () => (
  <BrowserRouter>
    <Routes>
      <Route path="/" element={<MainApp />} />
      <Route path="/products" element={<Products />} />
      <Route path="/maintenance" element={<Maintenance />} /> {/* NEW ROUTE */}
      {/* ADD ALL CUSTOM ROUTES ABOVE THE CATCH-ALL "*" ROUTE */}
      <Route path="*" element={<NotFound />} />
    </Routes>
  </BrowserRouter>
);

export default Index;