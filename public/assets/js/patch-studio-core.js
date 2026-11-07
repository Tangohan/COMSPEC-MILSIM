// ATHENA Patch Studio v1 — shared, dependency-free composition and document operations.
const {materials,renderDefaults,relativeMatrix,createSurfaceRenderer} = await import(new URL('./patch-studio-surface.js' + new URL(import.meta.url).search, import.meta.url));
export {materials,renderDefaults};
export const shapes=['rectangle','rounded_rectangle','ellipse','triangle','hexagon','octagon','shield','chevron','banner','diamond','star','polygon'];
export const patterns=['multicam','woodland','desert','fabric','velcro','pvc','rubber','embroidery','stripes','dots'];
export const fonts=['Barlow Condensed','Roboto Condensed','Oswald','Saira Stencil One','Inter','JetBrains Mono'];
export const variables=['callsign','unit','group','rank','firstname','lastname','country','organization'];
export const clone=v=>JSON.parse(JSON.stringify(v));
export const uid=()=>globalThis.crypto.randomUUID().replaceAll('-','');
export const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&apos;'}[c]));
export const solid=color=>({type:'solid',color});
export function layer(type='shape',extra={}) {
  return {id:uid(),type,name:type==='text'?'Texte':type==='group'?'Groupe':'Forme',x:40,y:40,width:200,height:100,rotation:0,scaleX:1,scaleY:1,opacity:1,visible:true,locked:false,
    fill:solid('#414c38'),stroke:{color:'#b3bc91',width:0,opacity:1,style:'solid',position:'center'},
    ...(type==='shape'?{shape:'rounded_rectangle',radius:14,sides:6}:{}),
    ...(type==='text'?{content:'ATHENA',fontFamily:'Barlow Condensed',fontSize:54,fontWeight:700,italic:false,textCase:'none',letterSpacing:3,wordSpacing:0,lineHeight:1.2,align:'center',verticalAlign:'middle',curve:0,fill:solid('#e6e9dc')}:{}),
    ...(type==='group'?{children:[],mask:'none'}:{}),...extra};
}
export function template(kind='name') {
  const doc={version:1,canvas:{width:512,height:256,transparent:true,background:'#151a16'},variants:{standard:[]},render:{...renderDefaults},fallbacks:Object.fromEntries(variables.map(k=>[k,k==='callsign'?'SABER 1-1':k==='organization'?'ATHENA':'']))};
  if(kind==='blank') return doc;
  const square=['unit','circle','pvc','embroidered'].includes(kind);
  if(square) doc.canvas.height=512;
  const h=doc.canvas.height;
  const bg=layer('shape',{name:'Fond et bordure',x:10,y:10,width:492,height:h-20,shape:kind==='circle'?'ellipse':kind==='unit'?'shield':'rounded_rectangle',radius:24,fill:kind==='embroidered'?{type:'pattern',pattern:'embroidery',scale:1,color:'#414c38'}:solid(kind==='ir'?'#171b17':'#414c38'),stroke:{color:'#b6a477',width:8,opacity:1,style:kind==='embroidered'?'stitch':'double',position:'inside'}});
  const text=layer('text',{x:38,y:square?65:50,width:436,height:130,content:kind==='callsign'||kind==='name'?'{callsign}':'{organization}',fontSize:64});
  bg.material={type:kind==='embroidered'?'embroidery':kind==='pvc'?'flat':'cordura500',scale:1,roughness:.8,relief:.8,angle:0};
  bg.stroke={...bg.stroke,finish:kind==='pvc'?'heatcut':'embroidered',mode:'color',density:1.2,threadWidth:.6,threadAngle:10,relief:.8};
  if(kind==='embroidered')text.construction={mode:'embroidery',depth:.8,density:1.3,threadWidth:.58,threadAngle:20};
  if(kind==='laser'){
   bg.name='Tissu supérieur · MultiCam';bg.fill={type:'texture',textureId:'multicam-original',scale:1,repeat:false};
   bg.stroke={...bg.stroke,mode:'same',finish:'overlock'};
   text.name='Découpe · couche inférieure';text.content='{callsign}';text.fontSize=68;text.letterSpacing=4;
   text.fill=solid('#b7b390');text.material={type:'velcro',scale:.8,roughness:.9,relief:.5,angle:0};
   text.construction={mode:'laser',supportId:bg.id,depth:1.35,sealWidth:.45,sealColor:'#312d21'};
  }
  const layers=[bg,text];
  if(square) {
    layers.push(layer('shape',{name:'Étoile centrale',shape:'star',x:176,y:220,width:160,height:160,radius:0,fill:solid('#b3bc91')}));
    layers.push(layer('text',{name:'Devise',x:60,y:400,width:392,height:65,fontSize:28,letterSpacing:5,content:'HONNEUR • ENGAGEMENT'}));
  }
  if(kind==='circle'){text.curve=130;text.y=40;text.height=180;}
  if(kind==='pvc') bg.shadow={color:'#000000aa',blur:3,x:2,y:4};
  doc.variants.standard=layers;return doc;
}
export class History {
  constructor(doc,limit=80){this.limit=limit;this.reset(doc);}
  reset(doc){this.items=[JSON.stringify(doc)];this.index=0;}
  push(doc){const s=JSON.stringify(doc);if(s===this.items[this.index])return;this.items.splice(this.index+1);this.items.push(s);if(this.items.length>this.limit)this.items.shift();this.index=this.items.length-1;}
  undo(){if(this.index>0)return JSON.parse(this.items[--this.index]);}
  redo(){if(this.index<this.items.length-1)return JSON.parse(this.items[++this.index]);}
}
export function walk(layers,fn,parent=null){for(const l of layers){fn(l,parent);if(l.children)walk(l.children,fn,l);}}
export function find(layers,id){let out;walk(layers,l=>{if(l.id===id)out=l;});return out;}
export function duplicateMany(objects){
 const out=clone(objects),ids=new Map();walk(out,l=>{const previous=l.id;l.id=uid();ids.set(previous,l.id);});
 walk(out,l=>{if(l.construction?.supportId&&ids.has(l.construction.supportId))l.construction.supportId=ids.get(l.construction.supportId);});
 for(const l of out){l.name+=' copie';l.x+=12;l.y+=12;}return out;
}
export const duplicate=l=>duplicateMany([l])[0];
// A cut and its textile support form one fabrication scope. Never silently lose a cut on grouping.
export function checkConstructionScope(layers,ids){
 const chosen=new Set(ids);for(const l of layers)if(l.construction?.mode==='laser'&&chosen.has(l.id)!==chosen.has(l.construction.supportId))throw new Error('Sélectionnez ensemble la découpe laser et son support pour les grouper.');
}
export function repairConstructions(layers){
 const ids=new Set(layers.filter(l=>l.type==='shape').map(l=>l.id));
 for(const l of layers){if(l.construction?.mode==='laser'&&!ids.has(l.construction.supportId))l.construction={...l.construction,mode:'print',supportId:undefined};if(l.children)repairConstructions(l.children);}
}
export function bounds(l){return {x:l.x,y:l.y,width:l.width*l.scaleX,height:l.height*l.scaleY};}
export function group(layers,ids){
 const chosen=layers.filter(l=>ids.includes(l.id)&&!l.locked);if(chosen.length<2)return null;
 checkConstructionScope(layers,chosen.map(l=>l.id));
 const boxes=chosen.map(bounds),x=Math.min(...boxes.map(b=>b.x)),y=Math.min(...boxes.map(b=>b.y));
 const g=layer('group',{x,y,width:Math.max(...boxes.map(b=>b.x+b.width))-x,height:Math.max(...boxes.map(b=>b.y+b.height))-y,children:chosen.map(l=>({...l,x:l.x-x,y:l.y-y}))});
 const at=layers.findIndex(l=>ids.includes(l.id));for(let i=layers.length-1;i>=0;i--)if(chosen.includes(layers[i]))layers.splice(i,1);layers.splice(at,0,g);return g;
}
export function ungroup(layers,id){
 const i=layers.findIndex(l=>l.id===id),g=layers[i];if(!g||g.type!=='group'||g.rotation!==0||g.scaleX!==1||g.scaleY!==1)return false;
 layers.splice(i,1,...g.children.map(l=>({...l,x:l.x+g.x,y:l.y+g.y,opacity:l.opacity*g.opacity})));return true;
}
export function resolve(content,values={},fallbacks={}){return content.replace(/\{(callsign|unit|group|rank|firstname|lastname|country|organization)\}/g,(_,k)=>values[k]||fallbacks[k]||`{${k}}`);}
export function geometry(l){
 const w=l.width,h=l.height,cx=w/2,cy=h/2;
 const poly=(n,star=false)=>{const a=[];for(let i=0;i<(star?n*2:n);i++){const t=-Math.PI/2+i*Math.PI*2/(star?n*2:n),r=star&&i%2?0.43:1;a.push(`${cx+Math.cos(t)*cx*r},${cy+Math.sin(t)*cy*r}`);}return `<polygon points="${a.join(' ')}"/>`;};
 switch(l.shape){
 case 'ellipse':return `<ellipse cx="${cx}" cy="${cy}" rx="${cx}" ry="${cy}"/>`;
 case 'triangle':return poly(3);case 'hexagon':return poly(6);case 'octagon':return poly(8);case 'polygon':return poly(l.sides||5);case 'star':return poly(5,true);
 case 'diamond':return `<path d="M${cx} 0 L${w} ${cy} ${cx} ${h} 0 ${cy}Z"/>`;
 case 'shield':return `<path d="M0 0H${w}V${h*.52}Q${w} ${h*.8} ${cx} ${h}Q0 ${h*.8} 0 ${h*.52}Z"/>`;
 case 'chevron':return `<path d="M0 0L${cx} ${h*.35} ${w} 0V${h*.65}L${cx} ${h} 0 ${h*.65}Z"/>`;
 case 'banner':return `<path d="M0 0H${w}L${w*.88} ${cy} ${w} ${h}H0L${w*.12} ${cy}Z"/>`;
 default:return `<rect width="${w}" height="${h}" rx="${l.shape==='rounded_rectangle'?Math.min(l.radius||0,cx,cy):0}"/>`;
 }
}
// Original deterministic procedural swatches; no external images or claimed camouflage certification.
export function patternSvg(name,color='#414c38'){
 if(['multicam','woodland','desert'].includes(name)){
  const p=name==='desert'?['#b8a078','#d4bd92','#7e6e51','#e1cfa7']:name==='woodland'?['#435039','#88774d','#242e24','#b3a279']:['#9b946b','#c1b58b','#626b45','#443f30'];
  let s=`<rect width="96" height="96" fill="${p[0]}"/>`;
  for(let i=0;i<18;i++){const x=(i*37)%96,y=(i*53)%96;s+=`<path d="M${x} ${y}q-17 -12 -23 2t12 18q17 6 25 -4t-14 -16Z" fill="${p[1+i%3]}"/>`;}
  return s;
 }
 const base=`<rect width="96" height="96" fill="${esc(color)}"/>`;
 if(name==='dots')return base+'<circle cx="12" cy="12" r="5" fill="#c3c6aa"/>';
 if(name==='stripes')return base+'<path d="M-96 96L96 -96M0 96L96 0M96 96L192 0" stroke="#a1a88b" stroke-width="16"/>';
 if(name==='pvc')return base+'<path d="M0 1H96" stroke="#ffffff" opacity=".12" stroke-width="3"/>';
 let s=base;
 const step=name==='velcro'?6:name==='rubber'?8:4;
 for(let i=0;i<96;i+=step)s+=`<path d="M0 ${i}H96M${i} 0V96" stroke="#000000" stroke-opacity=".22" stroke-width="1"/>`;
 if(name==='embroidery')for(let i=-96;i<192;i+=6)s+=`<path d="M${i} 0l96 96" stroke="#ffffff" stroke-opacity=".12"/>`;
 return s;
}
export function compose(doc,{variant='standard',values={},resources={},textures={},fontCss='',selection=null,idPrefix='ps',pixelWidth,pixelHeight,background='project'}={}){
 const defs=[];let seq=0;const next=()=>`${idPrefix}${++seq}`;
 const effects=createSurfaceRenderer({defs,next,settings:doc.render});
 const fill=(f,l)=>{
  if(!f||f.type==='none')return 'none';if(f.type==='solid')return esc(f.color);
  const id=next();
  if(f.type==='gradient'){
   const a=(f.angle||0)*Math.PI/180,x=Math.cos(a)*.5,y=Math.sin(a)*.5;
   defs.push(`<linearGradient id="${id}" x1="${.5-x}" y1="${.5-y}" x2="${.5+x}" y2="${.5+y}"><stop stop-color="${esc(f.color)}"/><stop offset="1" stop-color="${esc(f.color2)}"/></linearGradient>`);
  }else{
   const sc=f.scale||1,repeat=f.repeat!==false,texture=f.type==='texture'?textures[f.textureId]:null;
   if(f.type==='texture'&&(!texture||!texture.src))throw new Error('Texture indisponible : '+f.textureId);
   const tileW=texture?.tileWidth||96,tileH=texture?tileW*texture.height/texture.width:96;
   const pw=repeat?tileW:l.width/sc,ph=repeat?tileH:l.height/sc;
   let body=f.type==='asset'||texture?`<image href="${esc(texture?texture.src:resources[f.assetId]||'')}" width="${pw}" height="${ph}" preserveAspectRatio="xMidYMid slice"/>`:patternSvg(f.pattern,f.color);
   let patternAttrs=`width="${pw}" height="${ph}" patternTransform="translate(${f.offsetX||0} ${f.offsetY||0}) rotate(${f.rotation||0}) scale(${sc})"`;
   if(!repeat&&(texture||f.type==='asset')&&(doc.render||l.material||l.stroke?.mode||l.construction)){
    // A single image in a large non-repeating tile. Cover the rotated support, preserving its aspect ratio.
    const cx=l.width/2,cy=l.height/2,span=Math.hypot(l.width,l.height)+160,iw=span,ih=span;
    const pad=Math.ceil(span+Math.hypot(f.offsetX||0,f.offsetY||0)/sc),cell=pad*2;
    if(Math.hypot(f.offsetX||0,f.offsetY||0)>span*(sc+1))return 'none';
    body=`<image href="${esc(texture?texture.src:resources[f.assetId]||'')}" x="${cx-iw/2}" y="${cy-ih/2}" width="${iw}" height="${ih}" preserveAspectRatio="xMidYMid slice"/>`;
    patternAttrs=`x="${cx-pad}" y="${cy-pad}" width="${cell}" height="${cell}" viewBox="${cx-pad} ${cy-pad} ${cell} ${cell}" patternTransform="translate(${f.offsetX||0} ${f.offsetY||0}) translate(${cx} ${cy}) rotate(${f.rotation||0}) scale(${sc}) translate(${-cx} ${-cy})"`;
   }
   if((f.brightness??1)!==1||(f.contrast??1)!==1){const fid=next(),c=f.contrast??1,b=f.brightness??1;defs.push(`<filter id="${fid}" color-interpolation-filters="sRGB"><feComponentTransfer>${['R','G','B'].map(ch=>`<feFunc${ch} type="linear" slope="${c*b}" intercept="${(.5-.5*c)*b}"/>`).join('')}</feComponentTransfer></filter>`);body=`<g filter="url(#${fid})">${body}</g>`;}
   defs.push(`<pattern id="${id}" patternUnits="userSpaceOnUse" ${patternAttrs}>${body}</pattern>`);
  }return `url(#${id})`;
 };
 const textGeometry=l=>{
  let content=resolve(l.content,values,doc.fallbacks);if(l.textCase==='upper')content=content.toLocaleUpperCase('fr');if(l.textCase==='lower')content=content.toLocaleLowerCase('fr');
  const lines=content.split('\n'),lineH=l.fontSize*l.lineHeight,block=lines.length*lineH;
  const x=l.align==='center'?l.width/2:l.align==='right'?l.width:0,y=(l.verticalAlign==='middle'?(l.height-block)/2:l.verticalAlign==='bottom'?l.height-block:0)+l.fontSize*.84;
  const textAttrs=`font-family="${esc(l.fontAsset?'asset_'+l.fontAsset:l.fontFamily)}" font-size="${l.fontSize}" font-weight="${l.fontWeight}" font-style="${l.italic?'italic':'normal'}" letter-spacing="${l.letterSpacing}" word-spacing="${l.wordSpacing}" text-anchor="${l.align==='center'?'middle':l.align==='right'?'end':'start'}"`;
  if(l.curve){
   const id=next(),rise=-l.curve/360*l.width*.75;
   defs.push(`<path id="${id}" d="M0 ${l.height*.65} Q${l.width/2} ${l.height*.65+2*rise} ${l.width} ${l.height*.65}"/>`);
   return `<text ${textAttrs}><textPath href="#${id}" startOffset="${l.align==='center'?'50%':l.align==='right'?'100%':'0%'}">${esc(content.replaceAll('\n',' '))}</textPath></text>`;
  }
  return `<text ${textAttrs}>${lines.map((line,i)=>`<tspan x="${x}" y="${y+i*lineH}">${esc(line)||' '}</tspan>`).join('')}</text>`;
 };
 const legacyBorder=(l,geo,f)=>{
  const s=l.stroke||{width:0,color:'#000000',opacity:1};
  const style=`fill="${f}" fill-opacity="${l.fill?.opacity??1}" stroke="${esc(s.color)}" stroke-width="${s.width}" stroke-opacity="${s.opacity}" paint-order="stroke fill" stroke-linejoin="round"${['dashed','stitch'].includes(s.style)?` stroke-dasharray="${s.style==='stitch'?`${s.width*.7} ${s.width*.8}`:`${s.width*2} ${s.width*1.5}`}"`:''}`;
  let body;
  if(l.type==='shape'&&s.width>0&&s.position!=='center'){
   const cid=next();
   if(s.position==='inside'){defs.push(`<clipPath id="${cid}">${geo}</clipPath>`);body=`<g fill="${f}">${geo}</g><g ${style.replace(`stroke-width="${s.width}"`,`stroke-width="${s.width*2}"`)} clip-path="url(#${cid})">${geo}</g>`;}
   else{defs.push(`<mask id="${cid}" maskUnits="userSpaceOnUse" x="-100" y="-100" width="${l.width+200}" height="${l.height+200}"><rect x="-100" y="-100" width="${l.width+200}" height="${l.height+200}" fill="white"/><g fill="black">${geo}</g></mask>`);body=`<g ${style.replace(`fill="${f}"`,'fill="none"').replace(`stroke-width="${s.width}"`,`stroke-width="${s.width*2}"`)} mask="url(#${cid})">${geo}</g><g fill="${f}">${geo}</g>`;}
  }else body=`<g ${style}>${geo}</g>`;
  if(s.style==='double'&&s.width>0)body+=`<g fill="none" stroke="${f}" stroke-width="${s.width/3}">${geo}</g>`;
  return body;
 };
 const renderScope=scope=>scope.map(l=>{
  if(!l.visible||l.opacity===0)return '';
  let body='';
  if(l.type==='group'){
   body=renderScope(l.children);
   if(l.mask&&l.mask!=='none'){const id=next();defs.push(`<clipPath id="${id}">${geometry({...l,shape:l.mask})}</clipPath>`);body=`<g clip-path="url(#${id})">${body}</g>`;}
  }else if(l.type==='image'){
   const crop=l.crop||{x:0,y:0,width:1,height:1};
   body=`<svg width="${l.width}" height="${l.height}" viewBox="${crop.x} ${crop.y} ${crop.width} ${crop.height}" preserveAspectRatio="none"><image href="${esc(resources[l.assetId]||'')}" width="1" height="1" preserveAspectRatio="none"/></svg>`;
  }else if(l.type==='text'&&l.construction?.mode==='laser'){
   // Fabrication is rendered by the associated support, independently of layer stacking.
   return '';
  }else{
   const geo=l.type==='text'?textGeometry(l):geometry(l),f=fill(l.fill,l),s=l.stroke||{width:0};
   if(l.type==='shape'){
    const cuts=scope.filter(t=>t.type==='text'&&t.visible&&t.opacity>0&&t.construction?.mode==='laser'&&t.construction.supportId===l.id);
    let under='',edges='',mask='';
    if(cuts.length){
     const clip=next(),mid=next();defs.push(`<clipPath id="${clip}">${geo}</clipPath>`);
     const holes=cuts.map(t=>{
      const g=textGeometry(t),matrix=relativeMatrix(l,t).join(' '),tf=fill(t.fill,t);
      under+=`<g transform="matrix(${matrix})" opacity="${t.opacity}" fill="${tf}" fill-opacity="${t.fill?.opacity??1}">${g}${tf==='none'?'':effects.surface(t,g)}</g>`;
      edges+=`<g data-layer="${esc(t.id)}" transform="matrix(${matrix})" opacity="${t.opacity}">${effects.cutEdges(t,g)}</g>`;
      return `<g transform="matrix(${matrix})" fill="black" opacity="${t.opacity}">${g}</g>`;
     }).join('');
     defs.push(`<mask id="${mid}" mask-type="luminance" maskUnits="userSpaceOnUse" x="-100" y="-100" width="${l.width+200}" height="${l.height+200}"><rect x="-100" y="-100" width="${l.width+200}" height="${l.height+200}" fill="white"/><g clip-path="url(#${clip})">${holes}</g></mask>`);
     mask=`mask="url(#${mid})"`;under=`<g data-backing="true" clip-path="url(#${clip})">${under}</g>`;edges=`<g clip-path="url(#${clip})">${edges}</g>`;
    }
    const paint=s.mode==='same'?f:s.mode==='custom'?fill(s.fill,l):esc(s.color||'#000000');
    const modern=s.finish||s.mode;
    // Contact shadow excludes the solid SourceGraphic so a transparent laser backing stays transparent.
    body=effects.contact(geo,l)+under+`<g ${mask}><g fill="${f}" fill-opacity="${l.fill?.opacity??1}">${geo}</g>${effects.surface(l,geo)}${modern?effects.border(l,geo,paint,f):''}</g>`+edges;
    if(!modern){body=effects.contact(geo,l)+under+`<g ${mask}>${legacyBorder(l,geo,f)}${effects.surface(l,geo)}</g>`+edges;}
   }else{
    body=l.construction?.mode==='embroidery'?(s.width>0?legacyBorder({...l,fill:{type:'none'}},geo,'none'):'')+effects.embroidery(l,geo,f):legacyBorder(l,geo,f)+((l.fill?.type==='none')?'':effects.surface(l,geo));
   }
  }
  let filter='';if(l.shadow){const id=next(),sh=l.shadow;defs.push(`<filter id="${id}" x="-100%" y="-100%" width="300%" height="300%"><feDropShadow dx="${sh.x}" dy="${sh.y}" stdDeviation="${sh.blur/2}" flood-color="${esc(sh.color)}"/></filter>`);filter=`filter="url(#${id})"`;}
  return `<g data-layer="${esc(l.id)}" transform="translate(${l.x} ${l.y}) rotate(${l.rotation} ${l.width*l.scaleX/2} ${l.height*l.scaleY/2}) scale(${l.scaleX} ${l.scaleY})" opacity="${l.opacity}" ${filter}>${body}</g>`;
 }).join('');
 let layers=doc.variants[variant]||doc.variants.standard;
 if(selection){
  const selectScope=scope=>{
   const included=new Set(selection);
   for(const l of scope)if(l.construction?.mode==='laser'&&included.has(l.id))included.add(l.construction.supportId);
   for(const l of scope)if(l.construction?.mode==='laser'&&included.has(l.construction.supportId))included.add(l.id);
   return scope.flatMap(l=>{
    if(included.has(l.id))return [l];
    if(l.type==='group'){const children=selectScope(l.children);if(children.length)return [{...l,children}];}
    return [];
   });
  };
  layers=selectScope(layers);
 }
 const body=renderScope(layers),c=doc.canvas,bg=background==='light'?'#e8e6df':background==='dark'?'#141816':background==='transparent'||c.transparent?null:(c.background||'#000000');
 return `<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="${pixelWidth||c.width}" height="${pixelHeight||c.height}" viewBox="0 0 ${c.width} ${c.height}"><defs><style>${fontCss}</style>${defs.join('')}</defs>${bg?`<rect width="100%" height="100%" fill="${esc(bg)}"/>`:''}${body}</svg>`;
}
