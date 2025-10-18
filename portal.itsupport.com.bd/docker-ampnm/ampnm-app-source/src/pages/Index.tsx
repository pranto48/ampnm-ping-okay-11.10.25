import { BrowserRouter, Routes, Route } from "react-router-dom";
import MainApp from "./MainApp";
import NotFound from "./NotFound";
import Products from "./Products"; // Import the new Products page

const Index = () => (
  <BrowserRouter>
    <Routes>
      <Route path="/" element={<MainApp />} />
      <Route path="/products" element={<Products />} /> {/* NEW ROUTE */}
      {/* ADD ALL CUSTOM ROUTES ABOVE THE CATCH-ALL "*" ROUTE */}
      <Route path="*" element={<NotFound />} />
    </Routes>
  </BrowserRouter>
);

export default Index;