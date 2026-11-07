// Deterministic textile relief, contour sampling and fabrication effects. No external renderer.
export const materials={
 flat:{name:'Sans matière',pitch:0,sheen:0},
 cordura500:{name:'Cordura 500D',pitch:2.05,sheen:.24},
 cordura1000:{name:'Cordura 1000D',pitch:3.05,sheen:.19},
 multicam:{name:'Tissu camouflage',pitch:1.8,sheen:.19},
 nylon:{name:'Nylon tactique',pitch:1.4,sheen:.42},
 polyester:{name:'Polyester tactique',pitch:1.1,sheen:.33},
 velcro:{name:'Velcro · surface boucle',pitch:4.5,sheen:.09},
 embroidery:{name:'Textile brodé',pitch:1.35,sheen:.38},
};
export const renderDefaults={lightAngle:225,lightElevation:50,lightIntensity:.65,relief:.75,contactShadow:.45,quality:'high'};
const n=v=>Number(Number(v).toFixed(3))||0;
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&apos;'}[c]));
const random=i=>{const v=Math.sin(i*127.1+311.7)*43758.5453;return v-Math.floor(v);};
export function transformMatrix(l){
 const r=l.rotation*Math.PI/180,c=Math.cos(r),s=Math.sin(r),cx=l.width*l.scaleX/2,cy=l.height*l.scaleY/2;
 return [c*l.scaleX,s*l.scaleX,-s*l.scaleY,c*l.scaleY,l.x+cx-c*cx+s*cy,l.y+cy-s*cx-c*cy];
}
export function relativeMatrix(parent,child){
 const [a,b,c,d,e,f]=transformMatrix(parent),q=a*d-b*c,inv=[d/q,-b/q,-c/q,a/q,(c*f-d*e)/q,(b*e-a*f)/q],m=transformMatrix(child);
 return [inv[0]*m[0]+inv[2]*m[1],inv[1]*m[0]+inv[3]*m[1],inv[0]*m[2]+inv[2]*m[3],inv[1]*m[2]+inv[3]*m[3],inv[0]*m[4]+inv[2]*m[5]+inv[4],inv[1]*m[4]+inv[3]*m[5]+inv[5]].map(n);
}
export function contourPoints(l){
 const w=l.width,h=l.height,points=[];
 const add=(x,y)=>points.push([x,y]);
 const poly=(count,star=false)=>{for(let i=0;i<count*(star?2:1);i++){const a=-Math.PI/2+i*Math.PI*2/(count*(star?2:1)),r=star&&i%2 ? .43 : 1;add(w/2+Math.cos(a)*w/2*r,h/2+Math.sin(a)*h/2*r);}};
 const quad=(a,b,c,steps=40)=>{for(let i=1;i<=steps;i++){const t=i/steps;add((1-t)**2*a[0]+2*(1-t)*t*b[0]+t*t*c[0],(1-t)**2*a[1]+2*(1-t)*t*b[1]+t*t*c[1]);}};
 switch(l.shape){
  case 'ellipse':for(let i=0;i<256;i++){const a=i*Math.PI/128;add(w/2+Math.cos(a)*w/2,h/2+Math.sin(a)*h/2);}break;
  case 'triangle':poly(3);break;case 'hexagon':poly(6);break;case 'octagon':poly(8);break;case 'polygon':poly(l.sides||5);break;case 'star':poly(5,true);break;
  case 'diamond':points.push([w/2,0],[w,h/2],[w/2,h],[0,h/2]);break;
  case 'chevron':points.push([0,0],[w/2,h*.35],[w,0],[w,h*.65],[w/2,h],[0,h*.65]);break;
  case 'banner':points.push([0,0],[w,0],[w*.88,h/2],[w,h],[0,h],[w*.12,h/2]);break;
  case 'shield':points.push([0,0],[w,0],[w,h*.52]);quad([w,h*.52],[w,h*.8],[w/2,h]);quad([w/2,h],[0,h*.8],[0,h*.52]);break;
  default:{const r=l.shape==='rounded_rectangle'?Math.min(l.radius||0,w/2,h/2):0;if(!r){points.push([0,0],[w,0],[w,h],[0,h]);break;}
   for(const [cx,cy,start]of[[w-r,r,-90],[w-r,h-r,0],[r,h-r,90],[r,r,180]])for(let i=0;i<=24;i++){const a=(start+i*90/24)*Math.PI/180;add(cx+Math.cos(a)*r,cy+Math.sin(a)*r);} }
 }
 return points;
}
export function contourSamples(l,density=1,limit=1800){
 const points=contourPoints(l),edges=[];let total=0;
 for(let i=0;i<points.length;i++){const a=points[i],b=points[(i+1)%points.length],dx=b[0]-a[0],dy=b[1]-a[1],len=Math.hypot(dx,dy);if(len>1e-6){edges.push({a,b,len,start:total,tx:dx/len,ty:dy/len});total+=len;}}
 const count=Math.min(limit,Math.max(12,Math.ceil(total*density))),out=[];let edge=0;
 for(let i=0;i<count;i++){const at=(i+.5)*total/count;while(edge<edges.length-1&&at>edges[edge].start+edges[edge].len)edge++;const e=edges[edge],t=(at-e.start)/e.len;out.push({x:e.a[0]+(e.b[0]-e.a[0])*t,y:e.a[1]+(e.b[1]-e.a[1])*t,tx:e.tx,ty:e.ty,nx:-e.ty,ny:e.tx});}
 return out;
}
export function createSurfaceRenderer({defs,next,settings={}}){
 const config={...renderDefaults,...settings},cache=new Map();let remaining=config.quality==='high'?14000:8000;
 const light=config.lightAngle*Math.PI/180,lx=Math.cos(light),ly=Math.sin(light),elevation=Math.sin(config.lightElevation*Math.PI/180);
 const shade=(amount)=>n(amount*config.lightIntensity);
 const shadowFilter=(depth=1,opacity=.35)=>{const id=next();defs.push(`<filter id="${id}" x="-30%" y="-30%" width="160%" height="160%" color-interpolation-filters="sRGB"><feDropShadow dx="${n(-lx*depth)}" dy="${n(-ly*depth)}" stdDeviation="${n(depth*.55)}" flood-color="#000000" flood-opacity="${n(opacity)}"/></filter>`);return id;};
 function weave(l,profile){
  const m=l.material||{},scale=m.scale??1,angle=(m.angle||0)+(l.fill?.rotation||0),rough=m.roughness??.8;
  const amplitude=Math.min(1.5,(m.relief??.8)*config.relief);
  const key=JSON.stringify([m.type,scale,angle,rough,amplitude,config.quality]);if(cache.has(key))return cache.get(key);
  const id=next(),step=profile.pitch*scale,size=step*4;let body='';
  if(m.type==='velcro'){
   for(let i=0;i<48;i++){const x=random(i+1)*size,y=random(i+101)*size,dx=(random(i+201)-.5)*step,dy=step*(.3+random(i+301)*.45);const path=`M${n(x)} ${n(y)}q${n(dx-step*.35)} ${n(-dy)} ${n(step*.3)} ${n(-dy*.6)}t${n(-step*.2)} ${n(dy)}`;body+=`<path d="${path}" fill="none" stroke="#000000" stroke-width="${n(.65*scale)}" opacity=".34"/><path d="${path}" fill="none" stroke="#d5d2bf" stroke-width="${n(.17*scale)}" opacity=".27" transform="translate(0 -.18)"/>`;}
  }else{
   for(let y=0;y<4;y++)for(let x=0;x<4;x++){
    const px=x*step,py=y*step,vertical=m.type==='embroidery'||(x+y)%2===0;
    const d=vertical?`M${n(px+step*.5)} ${n(py-.1)}q${n(step*.09)} ${n(step*.5)} 0 ${n(step+0.2)}`:`M${n(px-.1)} ${n(py+step*.5)}q${n(step*.5)} ${n(-step*.09)} ${n(step+.2)} 0`;
    body+=`<path d="${d}" fill="none" stroke="#000000" stroke-width="${n(step*.81)}" opacity="${n(.14+rough*.13)}"/><path d="${d}" fill="none" stroke="#ffffff" stroke-width="${n(step*.5)}" opacity="${n(.08+profile.sheen*.13)}" transform="translate(-.13 -.15)"/>`;
    for(let f=-1;f<=1;f++)body+=`<path d="${d}" fill="none" stroke="${f===0?'#fff':'#000'}" stroke-width="${n(.09*scale)}" opacity="${f===0?.12:.18}" transform="translate(${n(vertical?f*step*.14:0)} ${n(vertical?0:f*step*.14)})"/>`;
   }
  }
  defs.push(`<pattern id="${id}" patternUnits="userSpaceOnUse" width="${n(size)}" height="${n(size)}" patternTransform="rotate(${n(angle+(m.type==='embroidery'?25:0))})"><g opacity="${n(amplitude)}">${body}</g></pattern>`);
  cache.set(key,id);return id;
 }
 function surface(l,geo){
  const profile=materials[l.material?.type];if(!profile||!profile.pitch)return '';
  const clip=next(),pattern=weave(l,profile),gradient=next(),noise=next(),m=l.material;
  defs.push(`<clipPath id="${clip}">${geo}</clipPath><linearGradient id="${gradient}" gradientUnits="userSpaceOnUse" x1="${n(l.width*(.5+lx*.5))}" y1="${n(l.height*(.5+ly*.5))}" x2="${n(l.width*(.5-lx*.5))}" y2="${n(l.height*(.5-ly*.5))}"><stop stop-color="#ffffff" stop-opacity="${shade((.13+profile.sheen*.15)*elevation)}"/><stop offset=".5" stop-color="#ffffff" stop-opacity="0"/><stop offset="1" stop-color="#000000" stop-opacity="${shade(.12)}"/></linearGradient>`);
  const rough=m.roughness??.8;
  defs.push(`<filter id="${noise}" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB"><feTurbulence type="fractalNoise" baseFrequency=".64" numOctaves="2" seed="19"/><feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 ${n(rough*.07)} ${n(rough*.07)} ${n(rough*.07)} 0 0"/></filter>`);
  return `<g data-surface="${esc(m.type)}" clip-path="url(#${clip})"><rect width="${l.width}" height="${l.height}" fill="url(#${pattern})"/><rect width="${l.width}" height="${l.height}" filter="url(#${noise})"/><rect width="${l.width}" height="${l.height}" fill="url(#${gradient})"/></g>`;
 }
 function contact(geo,l){
  if(!l.material||l.material.type==='flat'||config.contactShadow===0)return '';
  const amount=(l.material.relief??.7)*config.relief,id=next(),depth=(.4+amount)*(1.3-elevation*.5);
  defs.push(`<filter id="${id}" x="-30%" y="-30%" width="160%" height="160%" color-interpolation-filters="sRGB"><feGaussianBlur in="SourceAlpha" stdDeviation="${n(depth*.65)}" result="blur"/><feOffset in="blur" dx="${n(-lx*depth)}" dy="${n(-ly*depth)}"/><feComposite in2="SourceAlpha" operator="out" result="outside"/><feFlood flood-color="#000000" flood-opacity="${n(config.contactShadow*.65)}"/><feComposite in2="outside" operator="in"/></filter>`);
  return `<g fill="#000000" filter="url(#${id})">${geo}</g>`;
 }
 function border(l,geo,paint,underPaint){
  const s=l.stroke;if(!s||s.width<=0||s.finish==='none')return '';
  const w=s.width,finish=s.finish||'legacy',opacity=s.opacity*(s.mode==='custom'?(s.fill?.opacity??1):1);
  const bandWidth=s.position==='center'?w:2*w;
  let attr='';
  if(s.position==='inside'){const clip=next();defs.push(`<clipPath id="${clip}">${geo}</clipPath>`);attr=`clip-path="url(#${clip})"`;}
  if(s.position==='outside'){const mask=next();defs.push(`<mask id="${mask}" maskUnits="userSpaceOnUse" x="-100" y="-100" width="${l.width+200}" height="${l.height+200}"><rect x="-100" y="-100" width="${l.width+200}" height="${l.height+200}" fill="white"/><g fill="black">${geo}</g></mask>`);attr=`mask="url(#${mask})"`;}
  const band=`<g fill="none" stroke="${paint}" stroke-width="${bandWidth}" stroke-linejoin="round" ${finish==='legacy'&&['dashed','stitch'].includes(s.style)?`stroke-dasharray="${s.style==='stitch'?`${w*.7} ${w*.8}`:`${w*2} ${w*1.5}`}"`:""} ${attr}>${geo}</g>`;
  if(finish==='heatcut')return `<g data-finish="heatcut" opacity="${opacity}">${band}<g fill="none" stroke="#18150f" stroke-opacity=".48" stroke-width=".8">${geo}</g></g>`;
  if(finish==='legacy')return `<g opacity="${opacity}">${band}${s.style==='double'?`<g fill="none" stroke="${underPaint}" stroke-width="${bandWidth/3}" ${attr}>${geo}</g>`:''}</g>`;
  const density=s.density??1.2,count=Math.max(1,Math.min(2000,remaining)),samples=remaining>0?contourSamples(l,density,count):[];remaining-=samples.length;
  const low=s.position==='inside'?0:s.position==='outside'?-w:-w/2,high=low+w,angle=(s.threadAngle??12)*Math.PI/180,thread=s.threadWidth??.58;
  let paths='',shine='';
  samples.forEach((p,i)=>{
   const shift=Math.tan(angle)*w*.4,offset=(random(i+51)-.5)*.16;
   const x0=p.x+p.nx*(low+.2)+p.tx*(-shift+offset),y0=p.y+p.ny*(low+.2)+p.ty*(-shift+offset),x1=p.x+p.nx*(high-.2)+p.tx*shift,y1=p.y+p.ny*(high-.2)+p.ty*shift;
   const bend=finish==='overlock'?-w*.35:.25;
   const d=`M${n(x0)} ${n(y0)}Q${n((x0+x1)/2+p.tx*.18+p.nx*bend)} ${n((y0+y1)/2+p.ty*.18+p.ny*bend)} ${n(x1)} ${n(y1)}`;
   paths+=d;if(i%2===0)shine+=d;
  });
  const relief=(s.relief??.7)*config.relief,shadow=shadowFilter(.35+relief*.45,config.contactShadow*.65);
  return `<g data-finish="${finish}" data-thread-count="${samples.length}" opacity="${opacity}">${band}<g filter="url(#${shadow})"><path d="${paths}" fill="none" stroke="#000000" stroke-opacity=".38" stroke-width="${n(thread*1.5)}" stroke-linecap="round"/><path d="${paths}" fill="none" stroke="${paint}" stroke-width="${thread}" stroke-linecap="round"/><path d="${shine}" fill="none" stroke="#ffffff" stroke-opacity="${shade(.19)}" stroke-width="${n(thread*.22)}" transform="translate(${n(lx*.16)} ${n(ly*.16)})"/></g></g>`;
 }
 function embroidery(l,geo,paint){
  const c=l.construction||{},spacing=1/(c.density??1.25),id=next(),clip=next(),angle=c.threadAngle??20,thread=c.threadWidth??.55;
  defs.push(`<clipPath id="${clip}">${geo}</clipPath><pattern id="${id}" patternUnits="userSpaceOnUse" width="${n(spacing*4)}" height="${n(spacing*4)}" patternTransform="rotate(${angle})">${[0,1,2,3].map(i=>`<path d="M0 ${n(i*spacing)}h${n(spacing*4)}" stroke="#000000" stroke-opacity=".3" stroke-width="${thread}"/><path d="M0 ${n(i*spacing-.16)}h${n(spacing*4)}" stroke="#ffffff" stroke-opacity="${shade(.28)}" stroke-width="${n(thread*.28)}"/>`).join('')}</pattern>`);
  const filter=shadowFilter(.4+(c.depth??.8)*config.relief*.6,config.contactShadow*.8);
  return `<g data-construction="embroidery" opacity="${l.fill?.opacity??1}" filter="url(#${filter})"><g fill="${paint}" stroke="${paint}" stroke-width=".35">${geo}</g><g clip-path="url(#${clip})"><rect x="-1024" y="-1024" width="${l.width+2048}" height="${l.height+2048}" fill="url(#${id})"/></g></g>`;
 }
 function cutEdges(l,geo){
  const c=l.construction||{},depth=(c.depth??1.25)*config.relief,id=next(),opacity=config.contactShadow*.95;
  defs.push(`<filter id="${id}" x="-15%" y="-15%" width="130%" height="130%" color-interpolation-filters="sRGB"><feGaussianBlur in="SourceAlpha" stdDeviation="${n(.2+depth*.24)}" result="blur"/><feOffset in="blur" dx="${n(-lx*depth)}" dy="${n(-ly*depth)}" result="offset"/><feComposite in="SourceAlpha" in2="offset" operator="out" result="inside"/><feFlood flood-color="#000000" flood-opacity="${n(opacity)}"/><feComposite in2="inside" operator="in"/></filter>`);
  return `<g data-construction="laser"><g fill="none" stroke="${esc(c.sealColor||'#29261d')}" stroke-width="${c.sealWidth??.45}" stroke-opacity=".78" stroke-linejoin="round">${geo}</g><g fill="#ffffff" filter="url(#${id})">${geo}</g></g>`;
 }
 return {surface,contact,border,embroidery,cutEdges};
}
