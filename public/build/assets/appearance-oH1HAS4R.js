import{k as d,t,j as e,L as h}from"./app-Drun2YHd.js";import{c as r,a as n}from"./app-logo-icon-Cs8qe303.js";import{S as x,H as u}from"./layout-BoKN-DUy.js";import{A as y}from"./app-layout-CvBnCrX6.js";import"./button-DjEqPKbd.js";import"./label-B_2Yc27b.js";import"./index-BQfiYBWH.js";import"./app-toaster-DUJM3GYn.js";import"./index-CjLRDGol.js";import"./textarea-BLXNr82z.js";import"./language-switcher-dH5DfHNb.js";import"./briefcase-CQzqPL9n.js";/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const k=[["rect",{width:"20",height:"14",x:"2",y:"3",rx:"2",key:"48i651"}],["line",{x1:"8",x2:"16",y1:"21",y2:"21",key:"1svkeh"}],["line",{x1:"12",x2:"12",y1:"17",y2:"21",key:"vw1qmm"}]],b=r("Monitor",k);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const g=[["path",{d:"M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z",key:"a7tn18"}]],j=r("Moon",g);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const v=[["circle",{cx:"12",cy:"12",r:"4",key:"4exip2"}],["path",{d:"M12 2v2",key:"tus03m"}],["path",{d:"M12 20v2",key:"1lh1kg"}],["path",{d:"m4.93 4.93 1.41 1.41",key:"149t6j"}],["path",{d:"m17.66 17.66 1.41 1.41",key:"ptbguv"}],["path",{d:"M2 12h2",key:"1t8f8n"}],["path",{d:"M20 12h2",key:"1q8mjw"}],["path",{d:"m6.34 17.66-1.41 1.41",key:"1m8zz5"}],["path",{d:"m19.07 4.93-1.41 1.41",key:"1shlcs"}]],f=r("Sun",v);function M({className:s="",...o}){const{appearance:c,updateAppearance:p}=d(),i=[{value:"light",icon:f,label:t("Clair")},{value:"dark",icon:j,label:t("Sombre")},{value:"system",icon:b,label:t("Système")}];return e.jsx("div",{className:n("inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800",s),...o,children:i.map(({value:a,icon:l,label:m})=>e.jsxs("button",{onClick:()=>p(a),className:n("flex items-center rounded-md px-3.5 py-1.5 transition-colors",c===a?"bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100":"text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60"),children:[e.jsx(l,{className:"-ms-1 h-4 w-4"}),e.jsx("span",{className:"ms-1.5 text-sm",children:m})]},a))})}const N=[{title:"Paramètres d'apparence",href:"/settings/appearance"}];function E(){return e.jsxs(y,{breadcrumbs:N,children:[e.jsx(h,{title:t("Paramètres d'apparence")}),e.jsx(x,{children:e.jsxs("div",{className:"space-y-6",children:[e.jsx(u,{title:t("Paramètres d'apparence"),description:t("Mettez à jour les paramètres d'apparence de votre compte")}),e.jsx(M,{})]})})]})}export{E as default};
