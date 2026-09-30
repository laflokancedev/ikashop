const ADMIN_PWD = 'Forcia06';
const API_BASE = 'api.php';

const ICONS = {
  package: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 9.4l-9-5.19"/><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
  truck: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
  check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
  alert: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
  undo: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>'
};

const STATUS_MAP = {
  registered:        { label: 'Enregistré',           labelEn: 'Registered',        icon: 'package', step: 0, class: 'status-registered',        badge: 'badge-gray' },
  in_transit:        { label: 'En transit',            labelEn: 'In Transit',         icon: 'truck',   step: 2, class: 'status-in-transit',        badge: 'badge-blue' },
  out_for_delivery:  { label: 'En cours de livraison', labelEn: 'Out for Delivery',   icon: 'truck',   step: 3, class: 'status-out-for-delivery', badge: 'badge-orange' },
  delivered:         { label: 'Livré',                 labelEn: 'Delivered',          icon: 'check',   step: 4, class: 'status-delivered',         badge: 'badge-green' },
  delayed:           { label: 'Retardé',               labelEn: 'Delayed',            icon: 'alert',   step: 2, class: 'status-delayed',          badge: 'badge-red' },
  returned:          { label: 'Retourné',              labelEn: 'Returned',           icon: 'undo',    step: 2, class: 'status-returned',          badge: 'badge-gray' }
};

const CITIES_HUB = [
  'Paris-Roissy Sorting Center',
  'Lyon-Saint Exupéry Logistics Hub',
  'Marseille-Provence Platform',
  'Toulouse-Blagnac Regional Center',
  'Bordeaux-Mérignac Hub',
  'Lille-Lesquin Sorting Center',
  'Nantes-Atlantique Platform',
  'Strasbourg-Entzheim Hub'
];

// ── API FUNCTIONS ──

async function apiGetShipments() {
  const res = await fetch(API_BASE + '?action=shipments');
  if (!res.ok) return [];
  return await res.json();
}

async function apiTrack(tn) {
  const res = await fetch(API_BASE + '?action=track&tn=' + encodeURIComponent(tn));
  if (!res.ok) return null;
  return await res.json();
}

async function apiCreateShipment(data) {
  const res = await fetch(API_BASE + '?action=shipments', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Admin-Password': ADMIN_PWD },
    body: JSON.stringify(data)
  });
  return await res.json();
}

async function apiUpdateShipment(data) {
  const res = await fetch(API_BASE + '?action=shipments', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', 'X-Admin-Password': ADMIN_PWD },
    body: JSON.stringify(data)
  });
  return await res.json();
}

async function apiDeleteShipment(tn) {
  const res = await fetch(API_BASE + '?action=shipments&tn=' + encodeURIComponent(tn), {
    method: 'DELETE',
    headers: { 'X-Admin-Password': ADMIN_PWD }
  });
  return await res.json();
}

function generateTrackingNumber() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
  const nums = '0123456789';
  let id = 'WCH';
  for (let i = 0; i < 2; i++) id += chars[Math.floor(Math.random() * chars.length)];
  for (let i = 0; i < 8; i++) id += nums[Math.floor(Math.random() * nums.length)];
  return id;
}

function generateEvents(createdAt, estimatedDelivery, status, recipientCity) {
  const events = [];
  const created = new Date(createdAt);
  const now = new Date();
  const hub = CITIES_HUB[Math.floor(Math.random() * CITIES_HUB.length)];

  const steps = [
    { offset: 0,    title: 'Package registered',                        location: 'Wochen — Shipping Center' },
    { offset: 0.05, title: 'Picked up by Wochen',                       location: 'Wochen — Shipping Center' },
    { offset: 0.15, title: 'Departed shipping center',                   location: 'Wochen — Shipping Center' },
    { offset: 0.3,  title: 'Arrived at regional sorting center',         location: hub },
    { offset: 0.45, title: 'Processing in progress',                    location: hub },
    { offset: 0.55, title: 'Departed sorting center',                   location: hub },
    { offset: 0.7,  title: 'Arrived at local distribution center',      location: 'Distribution Center — ' + (recipientCity || 'Local') },
    { offset: 0.85, title: 'Out for delivery',                          location: recipientCity || 'Local' },
    { offset: 1.0,  title: 'Package delivered',                         location: recipientCity || 'Local' }
  ];

  let maxStep = steps.length;
  if (status === 'registered') maxStep = 2;
  else if (status === 'in_transit') maxStep = 6;
  else if (status === 'out_for_delivery') maxStep = 8;
  else if (status === 'delivered') maxStep = 9;
  else if (status === 'delayed') maxStep = 5;
  else if (status === 'returned') maxStep = 5;

  const elapsed = now.getTime() - created.getTime();
  const spanMs = Math.max(elapsed, 3600000);

  for (let i = 0; i < maxStep && i < steps.length; i++) {
    const ratio = maxStep > 1 ? steps[i].offset / steps[maxStep - 1].offset : 0;
    const evTime = new Date(created.getTime() + spanMs * ratio);
    events.push({
      date: evTime > now ? now.toISOString() : evTime.toISOString(),
      title: steps[i].title,
      location: steps[i].location
    });
  }

  if (status === 'delayed') {
    events.push({ date: now.toISOString(), title: 'Delivery delay — Package on hold', location: hub });
  }
  if (status === 'returned') {
    events.push({ date: now.toISOString(), title: 'Package returned to sender', location: 'Wochen — Returns Center' });
  }

  return events.reverse();
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}

function formatDateTime(iso) {
  const d = new Date(iso);
  return d.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' })
    + ' at ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function getStatusStep(status) {
  return STATUS_MAP[status]?.step ?? 0;
}
