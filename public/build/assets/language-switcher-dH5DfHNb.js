import{u as n,e as o,j as a,f as l,S as c}from"./app-Drun2YHd.js";import{c as u}from"./app-logo-icon-Cs8qe303.js";/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const d=[["path",{d:"m5 8 6 6",key:"1wu5hv"}],["path",{d:"m4 14 6-6 2-3",key:"1k1g8d"}],["path",{d:"M2 5h12",key:"or177f"}],["path",{d:"M7 2h1",key:"1t2jsx"}],["path",{d:"m22 22-5-10-5 10",key:"don7ne"}],["path",{d:"M14 18h6",key:"1m8k6r"}]],h=u("Languages",d);function i({className:s=""}){const t=n(),r=o();return a.jsxs("label",{className:`inline-flex items-center gap-2 text-sm ${s}`,children:[a.jsx(h,{className:"text-muted-foreground h-4 w-4","aria-hidden":!0}),a.jsx("span",{className:"sr-only",children:r("Langue")}),a.jsx("select",{className:"border-input bg-background h-9 rounded-md border px-2 text-sm",value:t,onChange:e=>c.put("/locale",{locale:e.target.value},{preserveScroll:!0,preserveState:!1}),children:l.map(e=>a.jsx("option",{value:e.value,lang:e.value,children:e.label},e.value))})]})}export{i as L};
