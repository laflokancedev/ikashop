const PRODUCTS = [
  // ── MERCEDES ──
  { id: 'merc-1', brand: 'mercedes', name: 'Mercedes A · C · E · CLA', subtitle: 'GLA · W205 · SLK — from 2012', material: 'Carbon & Alcantara', price: 64.99, img: 'img/mercedes/a-c-e-cla.jpg', badge: null },
  { id: 'merc-2', brand: 'mercedes', name: 'Mercedes A · C · E · GLC', subtitle: 'CLA · GLA · W205 · W176 AMG — from 2014', material: 'Carbon & Alcantara', price: 69.99, img: 'img/mercedes/a-c-e-glc.jpg', badge: null },
  { id: 'merc-3', brand: 'mercedes', name: 'Mercedes A · B · C · E', subtitle: 'CLA · GLA · GLB · GLE — from 2018', material: 'Carbon & Alcantara', price: 74.99, img: 'img/mercedes/a-b-c-e.jpg', badge: null },
  { id: 'merc-4', brand: 'mercedes', name: 'Mercedes A · C · E · GLS', subtitle: 'CLA · GLA · GLE — from 2018', material: 'Forged Carbon & Alcantara', price: 79.99, img: 'img/mercedes/a-c-e-gls.jpg', badge: null },

  { id: 'merc-6', brand: 'mercedes', name: 'Full Black Leather', subtitle: 'A · B · C · E · CLA · GLA · GLB · GLE — from 2018', material: 'Vollleder Schwarz', price: 87.99, img: 'img/mercedes/full-black-leather.jpg', badge: 'In Stock' },
  { id: 'merc-7', brand: 'mercedes', name: 'Woven Carbon · Beige', subtitle: 'A · C · E · CLA · GLA · GLE · GLS — from 2018', material: 'Carbon geflochten · Leder Beige', price: 89.99, img: 'img/mercedes/woven-carbon-beige.jpg', badge: 'In Stock' },
  { id: 'merc-8', brand: 'mercedes', name: 'Beige Leather Grips', subtitle: 'A · C · E · CLA · GLA · GLE · GLS — from 2018', material: 'Griffe Leder Beige', price: 92.99, img: 'img/mercedes/beige-leather-grips.jpg', badge: 'In Stock' },
  { id: 'merc-9', brand: 'mercedes', name: 'Forged Carbon', subtitle: 'A · C · E · CLA · GLA · GLE · GLS — from 2018', material: 'Schmiedecarbon', price: 94.99, img: 'img/mercedes/forged-carbon.jpg', badge: 'In Stock' },
  { id: 'merc-10', brand: 'mercedes', name: 'Woven Carbon · Black', subtitle: 'A · C · E · CLA · GLA · GLE · GLS — from 2018', material: 'Alcantara Schwarz', price: 59.99, oldPrice: 89.99, img: 'img/mercedes/woven-carbon-black.jpg', badge: 'Sale' },

  // ── AUDI ──
  { id: 'audi-1', brand: 'audi', name: 'Audi A3 · S3 · RS3', subtitle: 'A4 · RS4 · A6 · A8 · Q3 · Q5 — from 2008', material: 'Carbon & Alcantara', price: 64.99, img: 'img/audi/a3-s3-rs3.jpg', badge: null },
  { id: 'audi-2', brand: 'audi', name: 'Audi A1 · A4 · A5', subtitle: 'A3 · S3 · RS3 · S4 · RS4 · S5 · RS5 — from 2012', material: 'Carbon & Alcantara', price: 69.99, img: 'img/audi/a1-a4-a5.jpg', badge: null },
  { id: 'audi-3', brand: 'audi', name: 'Audi A4 · A6 · Q7', subtitle: 'A5 · A7 · Q5 · Q8 · S · RS — from 2018', material: 'Carbon & Alcantara', price: 79.99, img: 'img/audi/a4-a6-q7.jpg', badge: null },
  { id: 'audi-4', brand: 'audi', name: 'Audi S3 · RS3 · Q8', subtitle: 'Q3 · Q5 · Q7 — from 2020', material: 'Carbon, Alcantara, ShiftLEDs', price: 89.99, img: 'img/audi/s3-rs3-q8.jpg', badge: null },
  { id: 'audi-5', brand: 'audi', name: 'Audi R8 · TT · TTS', subtitle: 'TT RS · TT MK3 — Premium', material: 'Forged Carbon · Alcantara · Custom Stitching', price: 94.99, img: 'img/audi/r8-tt-tts.jpg', badge: 'Premium' },
  { id: 'audi-6', brand: 'audi', name: 'Audi S3 · RS3 · Q3', subtitle: 'Q5 · Q7 · Q8 — from 2020', material: 'Matte Carbon · Carbon Matt', price: 59.99, oldPrice: 79.99, img: 'img/audi/s3-rs3-q3-matte.jpg', badge: 'Sale' },

  // ── VOLKSWAGEN ──
  { id: 'vw-1', brand: 'volkswagen', name: 'Golf 5 GTI · R32', subtitle: 'Jetta · Passat B6 · EOS — from 2003', material: 'Carbon & Alcantara', price: 59.99, img: 'img/vw/golf-5-gti.jpg', badge: null },
  { id: 'vw-2', brand: 'volkswagen', name: 'Golf 6 GTI · R', subtitle: 'Polo · Scirocco · Passat — from 2008', material: 'Carbon & Alcantara', price: 79.99, img: 'img/vw/golf-6-gti.jpg', badge: null },
  { id: 'vw-3', brand: 'volkswagen', name: 'Golf 7 GTI · R', subtitle: 'Polo · Scirocco · Tiguan — from 2013', material: 'Carbon, Alcantara, ShiftLEDs', price: 69.99, img: 'img/vw/golf-7-gti.jpg', badge: null },
  { id: 'vw-4', brand: 'volkswagen', name: 'Golf 8 GTI · R', subtitle: 'Passat · Tiguan · T-Roc — from 2020', material: 'Carbon, Alcantara, ShiftLEDs', price: 89.99, img: 'img/vw/golf-8-gti.jpg', badge: null },
  { id: 'vw-5', brand: 'volkswagen', name: 'Golf 7 GTI · R', subtitle: 'Polo · Scirocco · Tiguan — from 2013', material: 'Black Carbon · ShiftLEDs', price: 49.99, oldPrice: 60.00, img: 'img/vw/golf-7-gti-black.jpg', badge: 'Sale' },

  // ── BMW ──
  { id: 'bmw-1', brand: 'bmw', name: 'BMW G Series', subtitle: '1 · 2 · 3 · 4 · 5 · X · M — from 2019', material: 'Carbon & Alcantara', price: 89.99, img: 'img/bmw/g-series.jpg', badge: null },
  { id: 'bmw-2', brand: 'bmw', name: 'BMW F Series', subtitle: 'F20 · F30 · F32 · F10 · F15 · M2 · M3 · M4', material: 'Carbon & Alcantara', price: 79.99, img: 'img/bmw/f-series.jpg', badge: null },
  { id: 'bmw-3', brand: 'bmw', name: 'BMW 1 · 3 Series', subtitle: 'E81 · E87 · E90 · E92 · M — from 2005', material: 'Carbon & Alcantara · Custom Stitching', price: 69.99, img: 'img/bmw/e-series.jpg', badge: 'Classic' },
  { id: 'bmw-4', brand: 'bmw', name: 'Leather · Alcantara', subtitle: 'BMW G Series — from 2019', material: 'Leder · Alcantara', price: 84.99, img: 'img/bmw/leather-alcantara.jpg', badge: 'In Stock' },
  { id: 'bmw-5', brand: 'bmw', name: 'Woven Carbon · Black', subtitle: 'BMW G Series — from 2019', material: 'Carbon geflochten · Leder perforiert', price: 92.99, img: 'img/bmw/woven-carbon-black.jpg', badge: 'In Stock' },
  { id: 'bmw-6', brand: 'bmw', name: 'Full Carbon · M1 M2', subtitle: 'BMW G Series — from 2019', material: 'Vollgarnitur geflochten', price: 59.99, oldPrice: 94.99, img: 'img/bmw/full-carbon-m.jpg', badge: 'Sale' },

  { id: 'vw-6', brand: 'volkswagen', name: 'Forged Carbon · Blue Flakes', subtitle: 'Universal — All brands', material: 'Forged Carbon & Alcantara · Blue Flakes', price: 74.99, img: 'img/vw/forged-carbon-blue.jpg', badge: 'Premium' }
];

const BRANDS = {
  mercedes: { name: 'Mercedes-Benz', logo: 'M', color: '#1a1a1a', logoImg: 'img/logos/mercedes.png' },
  audi:     { name: 'Audi',          logo: 'A', color: '#bb0a30', logoImg: 'img/logos/audi.png' },
  volkswagen: { name: 'Volkswagen',  logo: 'V', color: '#001e50', logoImg: 'img/logos/volkswagen.png' },
  bmw:      { name: 'BMW',           logo: 'B', color: '#0066b1', logoImg: 'img/logos/bmw.svg' }
};

function getProductById(id) {
  return PRODUCTS.find(p => p.id === id);
}

function getProductsByBrand(brand) {
  return PRODUCTS.filter(p => p.brand === brand);
}
