const {shapes,patterns,fonts,materials,renderDefaults,variables,clone,uid,esc,solid,layer,template,History,walk,find,duplicate,duplicateMany,repairConstructions,group,ungroup,geometry,patternSvg,compose} = await import(new URL('./patch-studio-core.js' + new URL(import.meta.url).search, import.meta.url));
const $=s=>document.querySelector(s),root=$('#patch-studio'),api=root.dataset.api,csrf=$('meta[name=csrf-token]').content;
const labels={texture:'Texture image',asset:'Image importée',solid:'Couleur unie',gradient:'Dégradé',pattern:'Motif vectoriel',none:'Sans remplissage',rectangle:'Rectangle',rounded_rectangle:'Rectangle arrondi',ellipse:'Cercle / ovale',triangle:'Triangle',hexagon:'Hexagone',octagon:'Octogone',shield:'Écusson',chevron:'Chevron',banner:'Bannière',diamond:'Losange',star:'Étoile',polygon:'Polygone',multicam:'Camouflage simplifié (ancien)',woodland:'Woodland',desert:'Desert',fabric:'Tissu',velcro:'Velcro',pvc:'PVC',rubber:'Caoutchouc',embroidery:'Broderie',stripes:'Rayures',dots:'Points'};
Object.assign(labels,Object.fromEntries(Object.entries(materials).map(([k,m])=>[k,m.name])),{embroidered:'Brodée',overlock:'Surjetée',heatcut:'Découpée à chaud',legacy:'Classique',color:'Fil de couleur unie',same:'Camouflage identique au fond',custom:'Texture indépendante',print:'Impression',laser:'Laser Cut',high:'Fin · export',draft:'Rapide',inside:'Intérieure',center:'Centre',outside:'Extérieure',left:'Gauche',right:'Droite',top:'Haut',middle:'Milieu',bottom:'Bas',upper:'Majuscules',lower:'Minuscules'});
const state={doc:template(),project:null,variant:'standard',selected:[],scope:[],tab:'templates',dirty:false,zoom:1,pan:{x:60,y:80},clipboard:[],assets:[],resourceUrls:{},textureCatalog:[],textureResources:{},members:[],values:{},saving:null,conflict:false};
const history=new History(state.doc),resourceCache=new Map(),fontCache=new Map();let saveTimer,liveCommitTimer,toastTimer,textureCatalogPromise,space=false,drag=null,raf;
const layers=()=>{let a=state.doc.variants[state.variant]||[];for(const id of state.scope)a=find(a,id)?.children||[];return a;};
const selected=()=>layers().filter(l=>state.selected.includes(l.id));
function notify(message){$('#toast').textContent=message;$('#toast').hidden=false;clearTimeout(toastTimer);toastTimer=setTimeout(()=>$('#toast').hidden=true,6000);}
async function request(path,options={}){
 const res=await fetch(api+path,{credentials:'same-origin',...options,headers:{'X-CSRF-Token':csrf,...(options.body instanceof FormData?{}:{'Content-Type':'application/json'}),...options.headers}});
 if(!res.ok){let msg;try{msg=(await res.json()).error;}catch{msg=`Erreur HTTP ${res.status}`;}const e=new Error(msg||'Requête impossible');e.status=res.status;throw e;}return res;
}
const json=async(path,options={})=>(await request(path,options)).json();
function recovery(){try{localStorage.setItem('athena.patch.recovery.'+root.dataset.recovery,JSON.stringify({document:state.doc,project:state.project,variant:state.variant,at:Date.now()}));}catch{notify('Récupération locale indisponible : enregistrez sur ATHENA.');}}
function changed(){clearTimeout(liveCommitTimer);state.dirty=true;history.push(state.doc);recovery();$('#save-state').textContent='Modifications non enregistrées';clearTimeout(saveTimer);if(state.project&&!state.conflict)saveTimer=setTimeout(()=>save().catch(error),3500);render();}
function previewChanged(){
 state.dirty=true;$('#save-state').textContent='Modifications non enregistrées';clearTimeout(saveTimer);if(state.project&&!state.conflict)saveTimer=setTimeout(()=>save().catch(error),3500);
 clearTimeout(liveCommitTimer);const doc=state.doc;liveCommitTimer=setTimeout(()=>{if(state.doc===doc){history.push(doc);recovery();}},500);
 cancelAnimationFrame(raf);raf=requestAnimationFrame(updateArt);
}
function error(e){notify(e.message||String(e));}
async function save(copy=false){
 if(state.saving){await state.saving;if(!copy&&state.dirty)return save();return;}
 if(state.project&&!state.project.canEdit&&!copy)throw new Error('Projet en lecture seule. Enregistrez une copie.');
 history.push(state.doc);const snapshot=clone(state.doc),name=$('#project-name').value.trim()||'Sans titre',before=JSON.stringify(snapshot);
 $('#save-state').textContent='Enregistrement…';
 state.saving=(async()=>{
  try{
   const id=!copy&&state.project?.id;
   const payload={document:snapshot,name,...(id?{revision:Number(state.project.revision),visibility:state.project.visibility,status:state.project.status}:{})};
   const result=await json('/projects'+(id?'/'+id:''),{method:id?'PUT':'POST',body:JSON.stringify(payload)});
   state.project=result.project;state.conflict=false;state.dirty=JSON.stringify(state.doc)!==before||$('#project-name').value.trim()!==name;
   $('#save-state').textContent=state.dirty?'Modifications non enregistrées':`Enregistré · v${state.project.revision}`;
   if(!state.dirty){try{localStorage.removeItem('athena.patch.recovery.'+root.dataset.recovery);}catch{}}
   else recovery();
  }catch(e){if(e.status===409)state.conflict=true;$('#save-state').textContent=e.status===409?'Conflit · enregistrez une copie':'Échec de sauvegarde';throw e;}
  finally{state.saving=null;}
 })();return state.saving;
}
async function openProject(id){
 if(state.saving)await state.saving;
 if(state.dirty&&!confirm('Ouvrir ce projet et remplacer les modifications non enregistrées ?'))return;
 const {project}=await json('/projects/'+id);state.project=project;state.doc=project.document;state.variant='standard';state.scope=[];state.selected=[];state.dirty=false;state.conflict=false;history.reset(state.doc);$('#project-name').value=project.name;$('#save-state').textContent=`${project.canEdit?'Enregistré':'Lecture seule'} · v${project.revision}`;$('#modal').close();await loadResources();render();fit();
}
async function newProject(kind='blank'){
 if(state.saving){notify('Enregistrement en cours. Réessayez dans un instant.');return;}
 if(state.dirty&&!confirm('Remplacer les modifications non enregistrées par un nouveau projet ?'))return;
 state.doc=template(kind);state.project=null;state.scope=[];state.selected=[];state.variant='standard';state.conflict=false;$('#project-name').value='Nouveau patch';await loadResources();history.reset(state.doc);changed();fit();
}
const fontFiles={
 'Barlow Condensed':[['BarlowCondensed-Regular.ttf',400],['BarlowCondensed-Bold.ttf',700]],
 'Roboto Condensed':[['RobotoCondensed.ttf','100 900']],Oswald:[['Oswald.ttf','200 700']],
 'Saira Stencil One':[['SairaStencilOne-Regular.ttf',400]],Inter:[['../nine-line/inter-latin.woff2','100 900']],
 'JetBrains Mono':[['../nine-line/jetbrains-mono-latin.woff2',400]]};
const fontBase=new URL('../fonts/patch-studio/',import.meta.url);
const textureBase=new URL('../textures/patch-studio/',import.meta.url);
async function dataUrl(url){
 if(!resourceCache.has(url))resourceCache.set(url,(async()=>{const r=await fetch(url,{credentials:'same-origin'});if(!r.ok)throw new Error('Ressource inaccessible.');const b=await r.blob();return new Promise((resolve,reject)=>{const fr=new FileReader();fr.onload=()=>resolve(fr.result);fr.onerror=reject;fr.readAsDataURL(b);});})().catch(e=>{resourceCache.delete(url);throw e;}));return resourceCache.get(url);
}
async function ensureTextureCatalog(){
 if(!textureCatalogPromise)textureCatalogPromise=(async()=>{
  const r=await fetch(new URL('catalog.json',textureBase),{credentials:'same-origin'});
  if(!r.ok)throw new Error('Catalogue des textures indisponible.');const catalog=await r.json();
  if(catalog.version!==1||!Array.isArray(catalog.textures)||catalog.textures.length>100)throw new Error('Catalogue des textures invalide.');
  for(const t of catalog.textures){if(!/^[a-z0-9-]{1,64}$/.test(t.id)||!/^[a-z0-9-]+\.jpg$/.test(t.file)||!/^[a-f0-9]{64}$/.test(t.sha256)||!(t.width>0&&t.width<=4096&&t.height>0&&t.height<=4096))throw new Error('Texture de bibliothèque invalide.');labels[t.id]=t.name;}
  state.textureCatalog=catalog.textures;return catalog.textures;
 })().catch(e=>{textureCatalogPromise=null;throw e;});return textureCatalogPromise;
}
async function loadTexture(id){
 if(state.textureResources[id])return state.textureResources[id];
 const catalog=await ensureTextureCatalog(),t=catalog.find(t=>t.id===id);
 if(!t)throw new Error('Texture inconnue : '+id);
 const src=await dataUrl(new URL(t.file+'?v='+t.sha256.slice(0,16),textureBase).href);
 const bytes=Uint8Array.from(atob(src.split(',')[1]),c=>c.charCodeAt(0));
 const digest=[...new Uint8Array(await crypto.subtle.digest('SHA-256',bytes))].map(n=>n.toString(16).padStart(2,'0')).join('');
 if(digest!==t.sha256)throw new Error('Texture corrompue : '+t.name);
 return state.textureResources[id]={...t,src};
}
async function loadResources(){
 const ids=new Set();walk(Object.values(state.doc.variants).flat(),l=>{if(l.assetId)ids.add(l.assetId);if(l.fontAsset)ids.add(l.fontAsset);if(l.fill?.assetId)ids.add(l.fill.assetId);if(l.stroke?.fill?.assetId)ids.add(l.stroke.fill.assetId);});
 await Promise.all([...ids].map(async id=>{const data=await dataUrl(api+'/assets/'+id);state.resourceUrls[id]=data;}));
 const textures=new Set();walk(Object.values(state.doc.variants).flat(),l=>{if(l.fill?.type==='texture')textures.add(l.fill.textureId);if(l.stroke?.fill?.type==='texture')textures.add(l.stroke.fill.textureId);});await Promise.all([...textures].map(loadTexture));
 const jobs=[];walk(Object.values(state.doc.variants).flat(),l=>{if(l.fontAsset&&!fontCache.has(l.fontAsset)){const family='asset_'+l.fontAsset;const p=(async()=>{const f=new FontFace(family,`url("${state.resourceUrls[l.fontAsset]}")`);await f.load();document.fonts.add(f);return f;})();fontCache.set(l.fontAsset,p);jobs.push(p);}});await Promise.all(jobs);await document.fonts.ready;
}
async function exportFontCss(){
 const builtins=new Set(),assets=new Set();walk(state.doc.variants[state.variant],l=>{if(l.type==='text'){if(l.fontAsset)assets.add(l.fontAsset);else builtins.add(l.fontFamily);}});
 const out=[];
 for(const f of builtins)for(const [file,weight]of fontFiles[f])out.push(`@font-face{font-family:'${f}';font-weight:${weight};src:url('${await dataUrl(new URL(file,fontBase).href)}')}`);
 for(const id of assets)out.push(`@font-face{font-family:'asset_${id}';src:url('${state.resourceUrls[id]}')}`);
 return out.join('');
}
function updateArt(){
 $('#artwork').innerHTML=compose(state.doc,{variant:state.variant,values:state.values,resources:state.resourceUrls,textures:state.textureResources,idPrefix:'canvas-'});
 const c=state.doc.canvas;$('#artboard').style.width=c.width+'px';$('#artboard').style.height=c.height+'px';$('#selection').setAttribute('viewBox',`0 0 ${c.width} ${c.height}`);
 $('#canvas-size').textContent=`${c.width} × ${c.height} px`;
 let inner=layers().map(l=>{
  if(!l.visible)return '';
  const w=l.width*l.scaleX,h=l.height*l.scaleY,sel=state.selected.includes(l.id),r=4/state.zoom;
  return `<g data-wrap="${l.id}" transform="translate(${l.x} ${l.y}) rotate(${l.rotation} ${w/2} ${h/2})">${l.locked?'':`<rect class="ps-hit" data-hit="${l.id}" width="${w}" height="${h}"/>`}${sel?`<rect class="ps-box" width="${w}" height="${h}"/><path class="ps-box" d="M${w/2} 0v-${22/state.zoom}"/><circle class="ps-handle ps-rotate" data-rotate="${l.id}" cx="${w/2}" cy="${-22/state.zoom}" r="${r}"/><rect class="ps-handle" data-resize="${l.id}" x="${w-r}" y="${h-r}" width="${r*2}" height="${r*2}"/>`:''}</g>`;
 }).join('');
 let a=state.doc.variants[state.variant];const wrappers=[];
 for(const id of state.scope){const g=find(a,id);if(!g)break;wrappers.push(`<g transform="translate(${g.x} ${g.y}) rotate(${g.rotation} ${g.width*g.scaleX/2} ${g.height*g.scaleY/2}) scale(${g.scaleX} ${g.scaleY})">`);a=g.children;}
 $('#selection').innerHTML=wrappers.join('')+`<g id="selection-scope">${inner}</g>`+'</g>'.repeat(wrappers.length);
 updateView();
}
function render(){
 $('#variant').value=state.variant;updateArt();renderProperties();renderLayers();$('#selection-state').textContent=state.selected.length?`${state.selected.length} objet(s) sélectionné(s)`:'Aucun objet sélectionné';
 const readonly=state.project&&!state.project.canEdit;$('#viewport').dataset.readonly=readonly?'true':'false';
 $('#properties').querySelectorAll('input,select,textarea,button').forEach(el=>{if(readonly&&!el.dataset.read)el.disabled=true;});
}
function updateView(){
 $('#artboard').style.transform=`translate(${state.pan.x}px,${state.pan.y}px) scale(${state.zoom})`;$('#zoom').value=state.zoom*100;$('#zoom-value').value=Math.round(state.zoom*100)+' %';drawRulers();
}
function fit(){const v=$('#viewport').getBoundingClientRect(),c=state.doc.canvas;state.zoom=Math.max(.15,Math.min(2,(v.width-100)/c.width,(v.height-130)/c.height));state.pan={x:(v.width-c.width*state.zoom)/2,y:(v.height-c.height*state.zoom)/2};render();}
function drawRulers(){
 for(const axis of ['x','y']){const el=$('#ruler-'+axis),len=axis==='x'?$('#viewport').clientWidth-22:$('#viewport').clientHeight-22;el.width=axis==='x'?len:22;el.height=axis==='x'?22:len;const ctx=el.getContext('2d');ctx.fillStyle='#8f9d85';ctx.strokeStyle='#56634f';ctx.font='9px monospace';const pan=state.pan[axis]-22,step=state.zoom<.4?128:state.zoom<1?64:32;for(let n=Math.floor(-pan/state.zoom/step)*step;n<(len-pan)/state.zoom;n+=step){const p=pan+n*state.zoom;ctx.beginPath();if(axis==='x'){ctx.moveTo(p,15);ctx.lineTo(p,22);ctx.fillText(n,p+3,11);}else{ctx.moveTo(15,p);ctx.lineTo(22,p);ctx.save();ctx.translate(10,p+3);ctx.rotate(-Math.PI/2);ctx.fillText(n,0,0);ctx.restore();}ctx.stroke();}}
}
const get=(o,path)=>path.split('.').reduce((v,k)=>v?.[k],o);
function set(o,path,v){const parts=path.split('.'),last=parts.pop();for(const p of parts){o[p]??={};o=o[p];}o[last]=v;}
function input(label,path,value,type='number',extra=''){
 if(type==='select')return `<label class="ps-field">${label}<select data-prop="${path}">${extra}</select></label>`;
 if(type==='textarea')return `<label class="ps-field ps-wide">${label}<textarea data-prop="${path}">${esc(value)}</textarea></label>`;
 return `<label class="ps-field${type==='text'?' ps-wide':''}">${label}<input data-prop="${path}" type="${type}" ${type==='checkbox'?(value?'checked':''):`value="${esc(value)}"`} ${extra}></label>`;
}
const defaultStroke=()=>({color:'#b3bc91',width:0,opacity:1,style:'solid',position:'center'});
const options=(arr,current)=>arr.map(v=>`<option value="${esc(v)}" ${v===current?'selected':''}>${esc(labels[v]||v)}</option>`).join('');
const section=(name,body,id)=>`<section class="ps-section" data-fabrication-section="${id}"><h3>${name}</h3><div class="ps-fields">${body}</div></section>`;
const materialControls=(l,prefix='material')=>{
 const m=l.material||{type:'flat'};
 return input('Matière',prefix+'.type',null,'select',options(Object.keys(materials),m.type))+input('Grain / tissage',prefix+'.scale',m.scale??1,'number','min="0.25" max="4" step="0.05"')+input('Orientation du fil °',prefix+'.angle',m.angle??0,'number','min="-360" max="360"')+input('Rugosité',prefix+'.roughness',m.roughness??.8,'number','min="0" max="1" step="0.05"')+input('Épaisseur / relief',prefix+'.relief',m.relief??.8,'number','min="0" max="2" step="0.05"');
};
function paintControls(f,prefix='fill',title='Texture / couleur'){
 f??={type:'none'};
 let out=`<h4 class="ps-wide">${title}</h4>`+input('Remplissage',prefix+'.type',null,'select',options(['solid','gradient','pattern','texture','asset','none'],f.type))+input('Couleur',prefix+'.color',(f.color||'#414c38').slice(0,7),'color');
 if(f.type==='gradient')out+=input('Seconde couleur',prefix+'.color2',f.color2||'#b3bc91','color')+input('Angle °',prefix+'.angle',f.angle||0);
 if(f.type==='pattern')out+=input('Motif',prefix+'.pattern',null,'select',options(patterns,f.pattern||'multicam'));
 if(f.type==='texture')out+=input('Camouflage',prefix+'.textureId',null,'select',options(state.textureCatalog.map(t=>t.id),f.textureId));
 if(f.type==='asset')out+=input('Image / texture importée',prefix+'.assetId',null,'select','<option value="">Choisir une image…</option>'+state.assets.filter(a=>a.mime.startsWith('image/')).map(a=>`<option value="${a.id}" ${f.assetId===a.id?'selected':''}>${esc(a.label)}</option>`).join(''));
 if(['texture','asset','pattern'].includes(f.type))out+=input('Échelle',prefix+'.scale',f.scale||1,'number','min="0.05" max="20" step="0.05"')+input('Orientation °',prefix+'.rotation',f.rotation||0)+input('Position X',prefix+'.offsetX',f.offsetX||0)+input('Position Y',prefix+'.offsetY',f.offsetY||0)+input('Répéter',prefix+'.repeat',f.repeat!==false,'checkbox')+input('Opacité du motif',prefix+'.opacity',f.opacity??1,'number','min="0" max="1" step="0.05"')+input('Contraste',prefix+'.contrast',f.contrast??1,'number','min="0" max="3" step="0.1"')+input('Luminosité',prefix+'.brightness',f.brightness??1,'number','min="0" max="3" step="0.1"');
 return out;
}
function borderControls(l,prefix='stroke'){
 const s=l.stroke||defaultStroke();
 let out=input('Finition',prefix+'.finish',null,'select',options(['embroidered','overlock','heatcut','none','legacy'],s.finish||'legacy'))+input('Bordure',prefix+'.mode',null,'select',options(['color','same','custom'],s.mode||'color'))+((!s.mode||s.mode==='color')?input('Couleur du fil',prefix+'.color',s.color.slice(0,7),'color'):'')+input('Épaisseur (px)',prefix+'.width',s.width,'number','min="0" max="80" step="0.5"')+input('Position',prefix+'.position',null,'select',options(['inside','center','outside'],s.position))+input('Opacité',prefix+'.opacity',s.opacity,'number','min="0" max="1" step="0.05"');
 if(s.mode==='same')out+='<p class="ps-help ps-wide">Camouflage continu : fond et bordure utilisent la même image, à la même échelle et à la même position.</p>';
 if(s.mode==='custom')out+=paintControls(s.fill,prefix+'.fill','Texture indépendante de la bordure');
 if(['embroidered','overlock'].includes(s.finish))out+=input('Épaisseur du fil',prefix+'.threadWidth',s.threadWidth??.6,'number','min="0.1" max="3" step="0.05"')+input('Densité des points',prefix+'.density',s.density??1.2,'number','min="0.15" max="3" step="0.05"')+input('Orientation des points °',prefix+'.threadAngle',s.threadAngle??12,'number','min="-70" max="70"')+input('Relief de la couture',prefix+'.relief',s.relief??.8,'number','min="0" max="2" step="0.05"');
 if(!s.finish||s.finish==='legacy')out+=input('Style classique',prefix+'.style',null,'select',options(['solid','double','dashed','stitch'],s.style));
 return out;
}
function renderProperties(){
 const l=selected()[0],st=l?.stroke||defaultStroke(),c=state.doc.canvas,laser=l?.type==='text'&&l.construction?.mode==='laser',support=laser?layers().find(s=>s.id===l.construction.supportId):null;
 $('#object-kind').textContent=l?({shape:'Forme',text:'Texte',image:'Image',group:'Groupe'}[l.type]):'Canevas';
 let html=state.scope.length?'<div class="ps-section"><button data-action="leave-group">← Sortir du groupe</button></div>':'';
 html+='<nav class="ps-property-nav" aria-label="Réglages de fabrication">'+['Support','Texture','Bordure','Texte','Rendu'].map((n,i)=>`<button data-property-section="${i}">${n}</button>`).join('')+'</nav>';
 let body='';
 if(!l){
  body+=input('Largeur du canevas','canvas.width',c.width,'number','min="32" max="2048" step="1"')+input('Hauteur du canevas','canvas.height',c.height,'number','min="32" max="2048" step="1"')+input('Fond transparent','canvas.transparent',c.transparent,'checkbox')+input('Couleur du fond','canvas.background',c.background.slice(0,7),'color');
  body+='<p class="ps-help ps-wide">Sélectionnez le support dans les calques pour régler sa forme, sa matière et sa bordure.</p>';
 }else{
  body+=input('Nom du calque','name',l.name,'text')+input('X','x',l.x)+input('Y','y',l.y)+input('Largeur','width',l.width,'number','min="1" max="4096"')+input('Hauteur','height',l.height,'number','min="1" max="4096"')+input('Rotation °','rotation',l.rotation)+input('Opacité','opacity',l.opacity,'number','min="0" max="1" step="0.05"')+input('Échelle X','scaleX',l.scaleX,'number','step="0.05" min="0.05" max="20"')+input('Échelle Y','scaleY',l.scaleY,'number','step="0.05" min="0.05" max="20"');
  if(l.type==='shape')body+=input('Forme','shape',null,'select',options(shapes,l.shape))+input('Rayon','radius',l.radius,'number','min="0" max="1024"')+input('Côtés','sides',l.sides,'number','min="3" max="24" step="1"')+materialControls(l);
  if(l.type==='group')body+=input('Masque','mask',null,'select',options(['none',...shapes],l.mask||'none'))+'<button data-action="enter-group">Éditer les objets</button>';
  if(l.type==='image'){const crop=l.crop||{x:0,y:0,width:1,height:1};body+='<h4 class="ps-wide">Recadrage (fractions)</h4>'+['x','y','width','height'].map(k=>input(k,'crop.'+k,crop[k],'number','min="0" max="1" step="0.01"')).join('');}
  if(laser){body+=input('Tissu supérieur','construction.supportId',null,'select',layers().filter(s=>s.type==='shape').map(s=>`<option value="${s.id}" ${s.id===l.construction.supportId?'selected':''}>${esc(s.name)}</option>`).join(''))+'<button data-action="select-support">Sélectionner le support</button>';if(support)body+=materialControls(support,'support.material');}
  body+=`<div class="ps-inline ps-wide">${[['align-left','← Bord'],['align-center','↔ Centre'],['align-right','Bord →'],['align-top','↑ Haut'],['align-middle','↕ Milieu'],['align-bottom','Bas ↓'],['distribute-x','Distribuer X'],['distribute-y','Distribuer Y']].map(([a,t])=>`<button data-action="${a}">${t}</button>`).join('')}</div>`;
 }
 html+=section('01 · Support',body,'0');
 body=l&&['text','shape'].includes(l.type)?paintControls(l.fill,'fill',laser?'Couche inférieure · visible dans la découpe':'Texture du calque'):'<p class="ps-help ps-wide">Les textures se règlent sur les formes et les textes. Choisissez une ressource à gauche ou importez votre textile.</p>';
 if(support)body+=paintControls(support.fill,'support.fill','Couche supérieure · tissu découpé');
 html+=section('02 · Texture',body,'1');
 body=l?.type==='shape'?borderControls(l):support?borderControls(support,'support.stroke'):l?.type==='text'?input('Couleur du contour','stroke.color',st.color.slice(0,7),'color')+input('Épaisseur','stroke.width',st.width,'number','min="0" max="80"')+input('Opacité','stroke.opacity',st.opacity,'number','min="0" max="1" step="0.05"')+input('Style','stroke.style',null,'select',options(['solid','double','dashed','stitch'],st.style)):'<p class="ps-help ps-wide">Sélectionnez une forme pour personnaliser sa finition.</p>';
 html+=section('03 · Bordure',body,'2');
 if(l?.type==='text'){
  const con=l.construction||{mode:'print'};
  body=input('Fabrication','construction.mode',null,'select',options(['print','embroidery','laser'],con.mode))+input('Texte / variables','content',l.content,'textarea')+input('Police','fontFamily',null,'select',options(fonts,l.fontFamily))+input('Taille','fontSize',l.fontSize,'number','min="4" max="512"')+input('Graisse','fontWeight',null,'select',options([400,500,600,700,800,900],l.fontWeight))+input('Italique','italic',l.italic,'checkbox')+input('Casse','textCase',null,'select',['none','upper','lower'].map(k=>`<option value="${k}" ${k===l.textCase?'selected':''}>${{none:'Conserver',upper:'Majuscules',lower:'Minuscules'}[k]}</option>`).join(''))+input('Espacement des lettres','letterSpacing',l.letterSpacing)+input('Espacement des mots','wordSpacing',l.wordSpacing)+input('Interligne','lineHeight',l.lineHeight,'number','step="0.1" min="0.5" max="4"')+input('Alignement','align',null,'select',options(['left','center','right'],l.align))+input('Vertical','verticalAlign',null,'select',options(['top','middle','bottom'],l.verticalAlign))+input('Courbure °','curve',l.curve,'number','min="-360" max="360"');
  if(l.fontAsset)body+='<p class="ps-help ps-wide">Police importée active. Choisir une police de la liste retire cette substitution.</p>';
  if(con.mode==='laser')body+='<h4 class="ps-wide">Découpe thermoscellée</h4>'+input('Profondeur','construction.depth',con.depth??1.35,'number','min="0" max="4" step="0.05"')+input('Largeur thermoscellée','construction.sealWidth',con.sealWidth??.45,'number','min="0" max="2" step="0.05"')+input('Couleur de la tranche','construction.sealColor',con.sealColor||'#312d21','color')+materialControls(l)+'<p class="ps-help ps-wide">Le texte découpe le support associé. La couche inférieure se règle dans Texture ; « sans remplissage » laisse la découpe transparente.</p>';
  if(con.mode==='embroidery')body+=input('Épaisseur du fil','construction.threadWidth',con.threadWidth??.55,'number','min="0.1" max="3" step="0.05"')+input('Densité des points','construction.density',con.density??1.25,'number','min="0.15" max="3" step="0.05"')+input('Orientation des fils °','construction.threadAngle',con.threadAngle??20,'number','min="-90" max="90"')+input('Épaisseur de broderie','construction.depth',con.depth??.8,'number','min="0" max="4" step="0.05"');
 }else body='<button data-action="add-text">＋ Ajouter un texte indépendant</button><p class="ps-help ps-wide">Impression, broderie ou découpe laser. Chaque texte conserve ses propres réglages.</p>';
 html+=section('04 · Texte',body,'3');
 const r={...renderDefaults,...state.doc.render};
 body=input('Direction de la lumière °','render.lightAngle',r.lightAngle,'number','min="-360" max="360"')+input('Élévation °','render.lightElevation',r.lightElevation,'number','min="10" max="90"')+input('Éclairage diffus','render.lightIntensity',r.lightIntensity,'range','min="0" max="1" step="0.05"')+input('Relief des matières','render.relief',r.relief,'range','min="0" max="2" step="0.05"')+input('Ombres de contact','render.contactShadow',r.contactShadow,'range','min="0" max="1" step="0.05"')+input('Détail de fabrication','render.quality',null,'select',options(['high','draft'],r.quality))+'<button data-action="preview">Prévisualiser</button><button data-action="export">Exporter en haute résolution</button>';
 if(l){body+=`<button data-action="shadow">${l.shadow?'Retirer':'Ajouter'} l’ombre du calque</button>`;if(l.shadow)body+=input('Couleur','shadow.color',l.shadow.color.slice(0,7),'color')+input('Flou','shadow.blur',l.shadow.blur,'number','min="0" max="30"')+input('X','shadow.x',l.shadow.x)+input('Y','shadow.y',l.shadow.y);}
 if(!l){body+=`<h4 class="ps-wide">Variables ATHENA</h4><select id="member-select" aria-label="Membre pour la prévisualisation"><option value="">Substitutions du modèle</option>${state.members.map(m=>`<option value="${m.userId}" ${state.memberId===m.userId?'selected':''}>${esc(m.label)}</option>`).join('')}</select><button data-action="members">Actualiser les membres</button>`+variables.map(v=>input('{'+v+'}','fallbacks.'+v,state.doc.fallbacks[v]||'','text')).join('');}
 html+=section('05 · Rendu',body,'4');$('#properties').innerHTML=html;
}
function renderLayers(){
 const a=layers();$('#layer-count').textContent=a.length;$('#layers').innerHTML=[...a].reverse().map(l=>`<div class="ps-layer${state.selected.includes(l.id)?' selected':''}" draggable="true" data-layer-row="${l.id}"><button data-layer-visible="${l.id}" aria-label="${l.visible?'Masquer':'Afficher'}">${l.visible?'◉':'○'}</button><span class="ps-layer-label">${esc(l.name)}</span><button data-layer-lock="${l.id}" aria-label="${l.locked?'Déverrouiller':'Verrouiller'}">${l.locked?'▣':'□'}</button>${l.type==='group'?'<span>▤</span>':''}</div>`).join('');
}
function add(l){editable();layers().push(l);state.selected=[l.id];changed();}
function editable(){if(state.project&&!state.project.canEdit)throw new Error('Projet en lecture seule. Utilisez Enregistrer sous.');}
async function refreshAssets(){state.assets=(await json('/assets')).assets;}
async function renderResources(){
 $('.ps-tabs').querySelectorAll('[data-tab]').forEach(b=>b.setAttribute('aria-selected',String(b.dataset.tab===state.tab)));
 const q=$('#resource-search').value.toLocaleLowerCase('fr'),tab=state.tab;let html='';
 const card=(name,body,attrs)=>name.toLocaleLowerCase('fr').includes(q)?`<button class="ps-resource-card" ${attrs}>${body}${esc(name)}</button>`:'';
 if(tab==='templates'){await loadTexture('multicam-original');html='<div class="ps-resource-grid">'+[['name','Name tape'],['laser','Laser Cut · MultiCam'],['callsign','Callsign ATHENA'],['unit','Écusson d’unité'],['circle','Patch circulaire'],['ir','IR visuel'],['pvc','Patch PVC'],['embroidered','Patch brodé'],['blank','Canevas vierge']].map(([k,n])=>card(n,compose(template(k),{textures:state.textureResources,idPrefix:'tpl-'+k+'-'}),`data-template="${k}"`)).join('')+'</div>';}
 if(tab==='shapes'||tab==='symbols')html='<div class="ps-resource-grid">'+(tab==='symbols'?['star','chevron','shield','diamond','triangle']:shapes).map(s=>card(labels[s],`<svg viewBox="-4 -4 108 88"><g fill="#b3bc91">${geometry({shape:s,width:100,height:80,radius:15,sides:7})}</g></svg>`,`data-shape="${s}"`)).join('')+'</div>';
 if(tab==='text')html=`<button data-action="add-text" class="ps-primary">＋ Ajouter un bloc de texte</button><p class="ps-help">Chaque texte possède sa police, son contour et son remplissage. Utilisez {callsign} ou {unit} pour un modèle dynamique.</p><div class="ps-resource-grid">${fonts.map(f=>card(f,`<span class="ps-swatch" style="font-family:'${f}';font-size:27px">ATHENA</span>`,`data-font="${f}"`)).join('')}</div><button data-action="upload">Importer une police TTF / OTF</button>`;
 if(tab==='patterns'||tab==='textures'){
  const textures=await ensureTextureCatalog();
  html='<h3 class="ps-texture-title">Textures MultiCam®</h3><div class="ps-resource-grid">'+textures.map(t=>card(t.name,`<img loading="lazy" src="${esc(new URL(t.file,textureBase).href)}" alt="${esc(t.name)}">`,`data-texture="${t.id}"`)).join('')+'</div><p class="ps-help">Textures image locales. Sélectionnez un texte ou une forme pour les y appliquer ; sans sélection, un fond est ajouté.</p><h3 class="ps-texture-title">Motifs et matières</h3><div class="ps-resource-grid">'+patterns.filter(p=>tab==='patterns'||['fabric','velcro','pvc','rubber','embroidery','multicam','woodland','desert'].includes(p)).map(p=>card(labels[p],`<svg viewBox="0 0 96 96">${patternSvg(p)}</svg>`,`data-pattern="${p}"`)).join('')+'</div><p class="ps-help">Appliquer à l’objet sélectionné. Sans sélection, ajoute un fond indépendant.</p>';
  html+='<div class="ps-resource-grid">'+[['#414c38','Ranger Green'],['#4b5320','Olive Drab'],['#8b7355','Coyote Brown'],['#171b17','Noir'],['#626b65','Gris']].map(([c,n])=>card(n,`<span class="ps-swatch" style="background:${c}"></span>`,`data-color="${c}"`)).join('')+'</div>';
 }
 if(tab==='images'){
  html='<button data-action="upload">＋ Importer une ressource</button><p class="ps-help">Ajouter : nouvel objet. Remplir : masque de la forme ou du texte sélectionné.</p><div class="ps-resource-grid">';
  for(const a of state.assets.filter(a=>a.label.toLowerCase().includes(q))){const font=a.mime.startsWith('font/');html+=`<div class="ps-resource-card">${font?'Aa':`<img loading="lazy" src="${esc(api+'/assets/'+a.id)}" alt="">`}<span>${esc(a.label)}</span><div class="ps-inline"><button data-asset-add="${a.id}">${font?'Police':'Ajouter'}</button>${font?'':`<button data-asset-fill="${a.id}">Remplir</button>`}</div></div>`;}
  html+='</div>';
 }
 if(tab==='organization')html='<p class="ps-help">Retrouvez les projets partagés de votre organisation, ouvrez-les ou créez une copie éditable.</p><button data-action="organization-library">Ouvrir la bibliothèque</button>';
 if(state.tab===tab)$('#resources').innerHTML=html;
}
function applyFill(fill){editable();let ls=selected().filter(l=>['shape','text'].includes(l.type));if(!ls.length){const c=state.doc.canvas,l=layer('shape',{name:'Fond',x:0,y:0,width:c.width,height:c.height,shape:'rectangle',fill});layers().unshift(l);state.selected=[l.id];}else ls.forEach(l=>l.fill=clone(fill));changed();}
async function uploadFile(file){
 editable();if(file.size>8388608)throw new Error('8 Mio maximum.');const font=/\.(ttf|otf)$/i.test(file.name),form=new FormData();form.append('file',file);
 if(font){if(!confirm('Confirmez-vous disposer des droits d’utilisation, de partage et d’incorporation de cette police ?'))return;form.append('fontLicense','yes');form.append('license',prompt('Licence / référence des conditions d’utilisation :')||'Droits confirmés par l’importateur');}
 const {asset}=await json('/assets',{method:'POST',body:form});await refreshAssets();state.tab='images';renderResources();await useAsset(asset.id,false);notify('Ressource importée.');
}
async function useAsset(id,fill){
 editable();const data=await dataUrl(api+'/assets/'+id);state.resourceUrls[id]=data;const a=state.assets.find(a=>a.id===id);
 if(a?.mime.startsWith('font/')){const ls=selected().filter(l=>l.type==='text');if(!ls.length)throw new Error('Sélectionnez un bloc de texte pour appliquer cette police.');const font=new FontFace('asset_'+id,`url("${data}")`);await font.load();document.fonts.add(font);fontCache.set(id,Promise.resolve(font));ls.forEach(l=>l.fontAsset=id);changed();}
 else if(fill)applyFill({type:'asset',assetId:id,scale:1,repeat:true});
 else{const im=new Image();im.src=data;await im.decode();const w=Math.min(280,im.width||200),h=w*(im.height||200)/(im.width||200);add(layer('image',{assetId:id,name:a?.label||'Image',width:w,height:h}));}
}
function modal(title,content){$('#modal-title').textContent=title;$('#modal-content').innerHTML=content;if(!$('#modal').open)$('#modal').showModal();}
async function library(filter='mine'){
 const projects=(await json('/projects')).projects;
 const show=()=>{
  const f=$('#library-filter')?.value||filter,q=$('#library-search')?.value.toLowerCase()||'';
  $('#project-list').innerHTML=projects.filter(p=>p.name.toLowerCase().includes(q)&&(f==='all'||f==='archives'?f==='all'||p.status!=='active':p.status==='active'&&(f==='organization'?p.visibility==='organization':f==='shared'?!!p.share_role:!p.share_role&&p.visibility==='private'))).map(p=>`<article class="ps-project-card"><h3>${esc(p.name)}</h3><p>v${p.revision} · ${esc(p.status)} · ${esc(p.visibility)}</p><button data-open="${p.id}">Ouvrir</button></article>`).join('')||'<p class="ps-help">Aucun projet dans cette vue.</p>';
 };
 modal('Bibliothèque ATHENA',`<div class="ps-inline"><select id="library-filter" aria-label="Collection">${[['mine','Mes patchs privés'],['organization','Organisation'],['shared','Partagés avec moi'],['archives','Archives / supprimés'],['all','Tous mes accès']].map(([k,n])=>`<option value="${k}" ${filter===k?'selected':''}>${n}</option>`).join('')}</select><input id="library-search" type="search" placeholder="Rechercher…" aria-label="Rechercher un projet"><button data-action="save-copy">Enregistrer sous</button><button data-action="import-project">Importer un projet JSON</button><button data-action="history">Historique</button><button data-action="project-settings">Gérer le projet ouvert</button></div><div id="project-list" class="ps-projects"></div>`);
 $('#library-filter').onchange=show;$('#library-search').oninput=show;show();
}
async function projectSettings(){
 if(!state.project)throw new Error('Enregistrez le projet avant de le gérer.');const p=state.project;
 modal('Gestion du projet',`<div class="ps-export-form"><p>${esc(p.name)} · v${p.revision}</p>${p.canManage?`<label>Visibilité<select id="project-visibility">${options(['private','organization'],p.visibility)}</select></label><label>État<select id="project-status">${options(['active','archived','deleted'],p.status)}</select></label><button data-action="apply-settings">Appliquer</button><hr><label>ID du membre<input id="share-user" type="number" min="1"></label><label>Droit<select id="share-role">${options(['reader','editor','remove'],'reader')}</select></label><button data-action="share">Accorder / retirer l’accès</button>`:'<p class="ps-help">Seul le propriétaire ou un administrateur autorisé gère les accès et les archives.</p>'}<button data-action="save-copy">Dupliquer ce projet</button><button data-action="download-json">Télécharger le projet JSON</button></div>`);
}
async function historyModal(){
 if(!state.project)throw new Error('Enregistrez le projet pour créer un historique.');const {versions}=await json('/projects/'+state.project.id+'/history');modal('Historique des versions',`<p class="ps-help">Restaurer crée une nouvelle révision au prochain enregistrement. Les versions antérieures sont conservées.</p><div class="ps-projects">${versions.map(v=>`<article class="ps-project-card"><h3>Version ${v.revision}</h3><p>${esc(v.created_at)} UTC</p><button data-restore="${v.revision}">Restaurer</button></article>`).join('')}</div>`);
}
function download(blob,name){const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}
async function raster({width=state.doc.canvas.width,height=state.doc.canvas.height,selection=false,background='project'}={}){
 if(!Number.isInteger(width)||!Number.isInteger(height)||width<32||height<32||width>4096||height>4096)throw new Error('Résolution de rendu : 32 à 4096 pixels par côté.');
 await loadResources();const css=await exportFontCss(),svg=compose(state.doc,{variant:state.variant,values:state.values,resources:state.resourceUrls,textures:state.textureResources,fontCss:css,selection:selection?state.selected:null,pixelWidth:width,pixelHeight:height,background});
 const url=URL.createObjectURL(new Blob([svg],{type:'image/svg+xml;charset=utf-8'}));
 try{const im=new Image();im.src=url;await im.decode();const c=document.createElement('canvas');c.width=width||state.doc.canvas.width;c.height=height||state.doc.canvas.height;const ctx=c.getContext('2d');const k=Math.min(c.width/im.width,c.height/im.height),w=im.width*k,h=im.height*k;ctx.drawImage(im,(c.width-w)/2,(c.height-h)/2,w,h);return c.toDataURL('image/png').split(',')[1];}finally{URL.revokeObjectURL(url);}
}
function exportModal(){
 modal('Exporter le patch',`<div class="ps-export-form"><label>Format<select id="export-format">${options(['png','paa','zip','pbo','publish'],'png')}</select></label><label>Résolution<select id="export-size"><option value="native">Canevas natif</option>${['256x128','512x256','512x512','1024x512','1024x1024','2048x1024','2048x2048','4096x2048','4096x4096'].map(s=>`<option value="${s}">${s.replace('x',' × ')}</option>`).join('')}</select></label><label>Fond de l’image<select id="export-background"><option value="project">Fond du projet</option><option value="transparent">Transparent · sans fond</option><option value="light">Clair · photo produit</option><option value="dark">Sombre · photo produit</option></select></label><label><span>Objets sélectionnés seulement (PNG)</span><input type="checkbox" id="export-selection" ${state.selected.length?'':'disabled'}></label><p class="ps-help">PNG jusqu’à 4096 px ; PAA, ZIP, PBO et publication jusqu’à 2048 px. Les proportions sont conservées ; le surplus reste transparent. ZIP contient PNG, PAA, projet, ressources, licences et addon. Publier rend cette révision disponible aux comptes autorisés dans ATAK. L’application 3D exige de distribuer et charger l’addon avant la partie.</p><p class="ps-help">Composition : ${state.variant==='night'?'nocturne · simulation visuelle':'standard'}. Le rendu utilise les données de prévisualisation actuelles.</p><button class="ps-primary" data-action="run-export">Générer</button><button data-action="batch">Série PNG des membres sélectionnés</button><button data-action="download-json">Projet JSON éditable</button></div>`);
}
async function runExport(){
 const button=$('[data-action="run-export"]');button.disabled=true;
 try{if(state.dirty||!state.project)await save();const format=$('#export-format').value,size=$('#export-size').value,selection=$('#export-selection').checked;
  if(selection&&format!=='png')throw new Error('L’export de sélection est disponible en PNG.');
  const [width,height]=size==='native'?[state.doc.canvas.width,state.doc.canvas.height]:size.split('x').map(Number);
  if(format!=='png'&&Math.max(width,height)>2048)throw new Error('Choisissez au plus 2048 px pour les formats Arma 3.');
  const png=await raster({width,height,selection,background:$('#export-background').value});
  const res=await request('/projects/'+state.project.id+'/export',{method:'POST',body:JSON.stringify({revision:Number(state.project.revision),format,variant:state.variant,png,selection,letterbox:true})});
  if(format==='publish'){const result=await res.json();notify('Révision publiée dans le catalogue ATAK : '+result.class);}else{download(await res.blob(),`ATHENA_${state.project.id}_${state.variant}.${format}`);notify('Export généré.');}
 }finally{button.disabled=false;}
}
async function batch(){
 if(!state.members.length)throw new Error('Chargez d’abord les membres depuis les propriétés du canevas.');
 const old=state.values;let count=0;try{for(const m of state.members){state.values=m.values;const png=await raster();download(new Blob([Uint8Array.from(atob(png),c=>c.charCodeAt(0))],{type:'image/png'}),`ATHENA_${m.userId}_${state.variant}.png`);count++;}}finally{state.values=old;render();}notify(`${count} PNG générés. Autorisez les téléchargements multiples dans le navigateur si nécessaire.`);
}
function align(action){
 editable();const ls=selected(),c=state.doc.canvas;if(!ls.length)return;
 if(action.startsWith('distribute')){if(ls.length<3)throw new Error('Sélectionnez au moins trois objets.');const axis=action.endsWith('x')?'x':'y',dim=axis==='x'?'width':'height',scale=axis==='x'?'scaleX':'scaleY',a=[...ls].sort((a,b)=>a[axis]-b[axis]),first=a[0][axis],last=a.at(-1),span=last[axis]+last[dim]*last[scale]-first,total=a.reduce((n,l)=>n+l[dim]*l[scale],0),gap=(span-total)/(a.length-1);let at=first;for(const l of a){l[axis]=at;at+=l[dim]*l[scale]+gap;}}
 else for(const l of ls){const w=l.width*l.scaleX,h=l.height*l.scaleY;if(action==='align-left')l.x=0;if(action==='align-center')l.x=(c.width-w)/2;if(action==='align-right')l.x=c.width-w;if(action==='align-top')l.y=0;if(action==='align-middle')l.y=(c.height-h)/2;if(action==='align-bottom')l.y=c.height-h;}
 changed();
}
const actions={
 'save':()=>save(),'save-copy':async()=>{await save(true);$('#modal').close();notify('Copie enregistrée.');},'new':()=>newProject(),
 'undo':()=>{editable();const d=history.undo();if(d){state.doc=d;if(!d.variants[state.variant])state.variant='standard';state.selected=[];state.scope=[];state.dirty=true;recovery();clearTimeout(saveTimer);if(state.project&&!state.conflict)saveTimer=setTimeout(()=>save().catch(error),3500);render();$('#save-state').textContent='Modifications non enregistrées';}},
 'redo':()=>{editable();const d=history.redo();if(d){state.doc=d;if(!d.variants[state.variant])state.variant='standard';state.selected=[];state.scope=[];state.dirty=true;recovery();clearTimeout(saveTimer);if(state.project&&!state.conflict)saveTimer=setTimeout(()=>save().catch(error),3500);render();$('#save-state').textContent='Modifications non enregistrées';}},
 'duplicate':()=>{editable();const ls=duplicateMany(selected().filter(l=>!l.locked));layers().push(...ls);repairConstructions(layers());state.selected=ls.map(l=>l.id);changed();},
 'delete':()=>{editable();const a=layers();for(let i=a.length-1;i>=0;i--)if(state.selected.includes(a[i].id)&&!a[i].locked)a.splice(i,1);repairConstructions(a);state.selected=[];changed();},
 'group':()=>{editable();const g=group(layers(),state.selected);if(g){state.selected=[g.id];changed();}},
 'ungroup':()=>{editable();for(const l of selected())if(!ungroup(layers(),l.id))throw new Error('Pour dégrouper, remettez la rotation à 0 et les échelles à 1.');state.selected=[];changed();},
 'enter-group':()=>{const l=selected()[0];if(l?.type==='group'){state.scope.push(l.id);state.selected=[];render();}},'leave-group':()=>{state.scope.pop();state.selected=[];render();},
 'night':()=>{editable();if(state.doc.variants.night&&!confirm('Remplacer la composition nocturne actuelle ?'))return;state.doc.variants.night=clone(state.doc.variants.standard);state.variant='night';state.scope=[];state.selected=[];changed();},
 'shadow':()=>{editable();for(const l of selected())if(l.shadow)delete l.shadow;else l.shadow={color:'#000000',blur:5,x:3,y:3};changed();},
 'select-support':()=>{const l=selected()[0],support=layers().find(s=>s.id===l?.construction?.supportId);if(support){state.selected=[support.id];render();}},
 'add-text':()=>add(layer('text')),'upload':()=>$('#asset-file').click(),
 'library':()=>library(),'organization-library':()=>library('organization'),'project-settings':projectSettings,'history':historyModal,
 'apply-settings':async()=>{editable();const p=state.project;const result=await json('/projects/'+p.id,{method:'PUT',body:JSON.stringify({revision:Number(p.revision),name:$('#project-name').value,document:state.doc,visibility:$('#project-visibility').value,status:$('#project-status').value})});state.project=result.project;state.dirty=false;$('#save-state').textContent=`Enregistré · v${state.project.revision}`;$('#modal').close();notify('Projet mis à jour.');},
 'share':async()=>{await json('/projects/'+state.project.id+'/share',{method:'POST',body:JSON.stringify({userId:Number($('#share-user').value),role:$('#share-role').value})});notify('Droits mis à jour.');},
 'members':async()=>{const ids=prompt('IDs ATHENA des membres à prévisualiser (séparés par une virgule). Sans droits RH, seul votre compte est retourné.','');if(ids===null)return;state.members=(await json('/variables'+(ids?'?users='+encodeURIComponent(ids):''))).members;render();notify(`${state.members.length} membre(s) autorisé(s) chargé(s).`);},
 'export':exportModal,'run-export':runExport,'batch':batch,
 'preview':async()=>{const png=await raster();modal('Aperçu · '+(state.variant==='night'?'simulation nocturne':'standard'),`<img class="ps-preview" src="data:image/png;base64,${png}" alt="Rendu du patch">`);},
 'download-json':()=>download(new Blob([JSON.stringify(state.doc,null,2)],{type:'application/json'}),'ATHENA_project.json'),
 'import-project':()=>$('#project-file').click(),'close-modal':()=>$('#modal').close(),
 'fit':fit,'actual':()=>{state.zoom=1;render();},'zoom-in':()=>{state.zoom=Math.min(4,state.zoom*1.15);render();},'zoom-out':()=>{state.zoom=Math.max(.15,state.zoom/1.15);render();},
 'fullscreen':()=>document.fullscreenElement?document.exitFullscreen():root.requestFullscreen(),
};
root.addEventListener('click',async e=>{
 try{
  const b=e.target.closest('button');if(!b)return;
  if(b.dataset.propertySection)$('#properties').querySelector(`[data-fabrication-section="${b.dataset.propertySection}"]`).scrollIntoView({block:'start',behavior:'instant'});
  if(b.dataset.action){if(b.dataset.action.startsWith('align-')||b.dataset.action.startsWith('distribute-'))align(b.dataset.action);else await actions[b.dataset.action]?.();}
  if(b.dataset.tab){state.tab=b.dataset.tab;$('.ps-tabs').querySelectorAll('button').forEach(x=>x.setAttribute('aria-selected',x===b?'true':'false'));await renderResources();}
  if(b.dataset.template)await newProject(b.dataset.template);
  if(b.dataset.shape)add(layer('shape',{shape:b.dataset.shape,name:labels[b.dataset.shape]}));
  if(b.dataset.texture){await loadTexture(b.dataset.texture);applyFill({type:'texture',textureId:b.dataset.texture,scale:1,repeat:false});}
  if(b.dataset.pattern)applyFill({type:'pattern',pattern:b.dataset.pattern,color:'#414c38',scale:1,repeat:true});
  if(b.dataset.color)applyFill(solid(b.dataset.color));
  if(b.dataset.font){const l=selected()[0];if(l?.type==='text'){editable();l.fontFamily=b.dataset.font;delete l.fontAsset;changed();}else add(layer('text',{fontFamily:b.dataset.font}));}
  if(b.dataset.assetAdd)await useAsset(b.dataset.assetAdd,false);
  if(b.dataset.assetFill)await useAsset(b.dataset.assetFill,true);
  if(b.dataset.open)await openProject(b.dataset.open);
  if(b.dataset.restore){editable();const result=await json('/projects/'+state.project.id+'/history?revision='+b.dataset.restore);state.doc=result.document;state.scope=[];state.selected=[];await loadResources();changed();$('#modal').close();}
  if(b.dataset.layerVisible){editable();const l=find(layers(),b.dataset.layerVisible);l.visible=!l.visible;changed();}
  if(b.dataset.layerLock){editable();const l=find(layers(),b.dataset.layerLock);l.locked=!l.locked;changed();}
 }catch(ex){error(ex);}
});
async function applyProperty(field,preview=false){
 const path=field.dataset.prop;if(!path)return;editable();
 const v=field.type==='checkbox'?field.checked:['number','range'].includes(field.type)||path==='fontWeight'?Number(field.value):field.value;
 if((field.type==='number'&&field.value==='')||(typeof v==='number'&&(!Number.isFinite(v)||!field.validity.valid)))return;
 const actual=path.replace(/^support\./,''),fillMatch=actual.match(/^(fill|stroke\.fill)\.(type|textureId|assetId)$/);
 if(fillMatch){if(fillMatch[2]==='textureId')await loadTexture(v);if(fillMatch[2]==='type'&&v==='texture'){const catalog=await ensureTextureCatalog();await loadTexture(catalog[0].id);}if(fillMatch[2]==='assetId'&&v){state.resourceUrls[v]=await dataUrl(api+'/assets/'+v);}}
 const targets=path.startsWith('canvas.')||path.startsWith('fallbacks.')||path.startsWith('render.')?[state.doc]:selected().map(l=>path.startsWith('support.')?layers().find(s=>s.id===l.construction?.supportId):l).filter(Boolean);
 for(const l of targets){
  if(l.locked)continue;
  if(actual.startsWith('stroke.'))l.stroke??=defaultStroke();
  if(actual.startsWith('fill.'))l.fill??={type:'none'};
  if(actual.startsWith('material.'))l.material??={type:'flat'};
  if(actual==='construction.mode'&&v==='laser'){
   const support=layers().find(s=>s.id===l.construction?.supportId)||layers().find(s=>s.type==='shape');
   if(!support)throw new Error('Ajoutez une forme comme tissu supérieur avant de choisir Laser Cut.');
   l.construction={...l.construction,mode:'laser',supportId:support.id,depth:l.construction?.depth??1.35,sealWidth:l.construction?.sealWidth??.45,sealColor:l.construction?.sealColor??'#312d21'};
   l.material??={type:'velcro',scale:.8,roughness:.9,relief:.5,angle:0};
  }else set(l,actual,v);
  if(actual==='construction.mode'&&v!=='laser')delete l.construction.supportId;
  if(actual==='stroke.mode'&&v==='custom')l.stroke.fill??=clone(l.fill||solid('#414c38'));
  if(fillMatch&&fillMatch[2]==='type'){
   const prefix=fillMatch[1],f=get(l,prefix);f.color??='#414c38';
   if(v==='gradient')f.color2??='#b3bc91';if(v==='pattern')f.pattern??='multicam';
   if(v==='texture'){f.textureId??=state.textureCatalog[0].id;f.repeat??=false;await loadTexture(f.textureId);}
   if(v==='asset'&&!f.assetId){const first=state.assets.find(a=>a.mime.startsWith('image/'));if(!first){f.type='solid';notify('Importez d’abord une image dans vos ressources.');}else{f.assetId=first.id;state.resourceUrls[first.id]=await dataUrl(api+'/assets/'+first.id);}}
  }
  if(actual==='fontFamily')delete l.fontAsset;
 }
 if(preview)previewChanged();else changed();
}
$('#properties').addEventListener('input',async e=>{if(!e.target.dataset.prop||!['text','textarea','number','range','color'].includes(e.target.type))return;try{await applyProperty(e.target,true);}catch(ex){error(ex);}});
$('#properties').addEventListener('change',async e=>{
 if(!e.target.dataset.prop){if(e.target.id==='member-select'){state.memberId=Number(e.target.value);state.values=state.members.find(m=>m.userId===state.memberId)?.values||{};updateArt();}return;}
 try{await applyProperty(e.target);}catch(ex){error(ex);renderProperties();}
});
$('#project-name').addEventListener('change',()=>{try{editable();changed();}catch(e){error(e);}});
$('#variant').onchange=()=>{const v=$('#variant').value;if(!state.doc.variants[v]){state.variant='standard';$('#variant').value='standard';notify('Créez d’abord la composition nocturne avec « Copier standard → nuit ».');return;}state.variant=v;state.scope=[];state.selected=[];render();};
$('#resource-search').oninput=()=>renderResources();$('#zoom').oninput=()=>{state.zoom=Number($('#zoom').value)/100;render();};$('#grid').onchange=()=>$('#canvas-grid').style.display=$('#grid').checked?'block':'none';$('#canvas-backdrop').onchange=()=>$('#artboard').dataset.backdrop=$('#canvas-backdrop').value;
$('#asset-file').onchange=async()=>{try{if($('#asset-file').files[0])await uploadFile($('#asset-file').files[0]);}catch(e){error(e);}finally{$('#asset-file').value='';}};
$('#project-file').onchange=async()=>{try{const f=$('#project-file').files[0];if(!f)return;if(f.size>1048576)throw new Error('Projet limité à 1 Mio.');const d=JSON.parse(await f.text());if(d.version!==1||!d.canvas||!d.variants?.standard)throw new Error('Projet v1 invalide.');
 // Server validation precedes rendering untrusted JSON.
 const result=await json('/projects',{method:'POST',body:JSON.stringify({name:f.name.replace(/\.json$/i,''),document:d})});await openProject(result.project.id);
 }catch(e){error(e);}finally{$('#project-file').value='';}};
$('#layers').addEventListener('click',e=>{if(e.target.closest('button'))return;const row=e.target.closest('[data-layer-row]');if(!row)return;if(e.shiftKey||e.ctrlKey)state.selected=state.selected.includes(row.dataset.layerRow)?state.selected.filter(id=>id!==row.dataset.layerRow):[...state.selected,row.dataset.layerRow];else state.selected=[row.dataset.layerRow];render();});
let reorderId;
$('#layers').addEventListener('dragstart',e=>{reorderId=e.target.closest('[data-layer-row]')?.dataset.layerRow;e.dataTransfer.setData('text/plain',reorderId||'');});
$('#layers').addEventListener('dragover',e=>e.preventDefault());
$('#layers').addEventListener('drop',e=>{e.preventDefault();try{editable();const id=e.target.closest('[data-layer-row]')?.dataset.layerRow,a=layers(),from=a.findIndex(l=>l.id===reorderId),to=a.findIndex(l=>l.id===id);if(from>=0&&to>=0&&from!==to){const [l]=a.splice(from,1);a.splice(to,0,l);changed();}}catch(ex){error(ex);}});
$('#layers').addEventListener('dblclick',e=>{const l=find(layers(),e.target.closest('[data-layer-row]')?.dataset.layerRow);if(l?.type==='group'){state.selected=[l.id];actions['enter-group']();}});
const point=(e)=>{const m=$('#selection-scope').getScreenCTM();return new DOMPoint(e.clientX,e.clientY).matrixTransform(m.inverse());};
$('#viewport').addEventListener('pointerdown',e=>{
 if(e.button!==0&&e.button!==1)return;
 if(space||e.button===1){drag={kind:'pan',start:{x:e.clientX,y:e.clientY},pan:{...state.pan}};e.currentTarget.setPointerCapture(e.pointerId);e.preventDefault();return;}
 const hit=e.target.closest('[data-hit],[data-resize],[data-rotate]');
 if(!hit){state.selected=[];render();return;}
 const id=hit.dataset.hit||hit.dataset.resize||hit.dataset.rotate,l=find(layers(),id);if(!l||l.locked)return;
 if(e.shiftKey&&hit.dataset.hit){state.selected=state.selected.includes(id)?state.selected.filter(x=>x!==id):[...state.selected,id];render();return;}
 if(!state.selected.includes(id))state.selected=[id];render();
 if(state.project&&!state.project.canEdit)return;
 const p=point(e);drag={kind:hit.dataset.resize?'resize':hit.dataset.rotate?'rotate':'move',id,start:p,objects:selected().filter(l=>!l.locked).map(clone),moved:false};
 const wrap=$(`#selection [data-wrap="${id}"]`);drag.matrix=wrap.getScreenCTM().inverse();drag.local=new DOMPoint(e.clientX,e.clientY).matrixTransform(drag.matrix);
 e.currentTarget.setPointerCapture(e.pointerId);e.preventDefault();
});
$('#viewport').addEventListener('pointermove',e=>{
 if(!drag)return;if(drag.kind==='pan'){state.pan={x:drag.pan.x+e.clientX-drag.start.x,y:drag.pan.y+e.clientY-drag.start.y};updateView();return;}
 const p=point(e),dx=p.x-drag.start.x,dy=p.y-drag.start.y,grid=$('#snap').checked&&!e.altKey?4:0;
 if(Math.abs(dx)+Math.abs(dy)<1&&!drag.moved)return;drag.moved=true;
 const snap=v=>grid?Math.round(v/grid)*grid:v;
 for(const original of drag.objects){const l=find(layers(),original.id);if(drag.kind==='move'){l.x=snap(original.x+dx);l.y=snap(original.y+dy);
  if($('#snap').checked){const c=state.doc.canvas;for(const target of [0,c.width/2-l.width*l.scaleX/2,c.width-l.width*l.scaleX])if(Math.abs(l.x-target)<5/state.zoom)l.x=target;for(const target of [0,c.height/2-l.height*l.scaleY/2,c.height-l.height*l.scaleY])if(Math.abs(l.y-target)<5/state.zoom)l.y=target;}
 }else if(l.id===drag.id){if(drag.kind==='resize'){const q=new DOMPoint(e.clientX,e.clientY).matrixTransform(drag.matrix);l.width=Math.min(4096,Math.max(1,snap(original.width+(q.x-drag.local.x)/l.scaleX)));l.height=e.shiftKey?l.width*original.height/original.width:Math.min(4096,Math.max(1,snap(original.height+(q.y-drag.local.y)/l.scaleY)));}else{const cx=l.x+l.width*l.scaleX/2,cy=l.y+l.height*l.scaleY/2;let angle=Math.atan2(p.y-cy,p.x-cx)*180/Math.PI+90;l.rotation=e.shiftKey?Math.round(angle/15)*15:Math.round(angle);}}}
 cancelAnimationFrame(raf);raf=requestAnimationFrame(updateArt);
});
function endDrag(){if(!drag)return;const changedObjects=drag.kind!=='pan'&&drag.moved;drag=null;if(changedObjects)changed();}
$('#viewport').addEventListener('pointerup',endDrag);$('#viewport').addEventListener('pointercancel',endDrag);
$('#viewport').addEventListener('wheel',e=>{e.preventDefault();const b=$('#viewport').getBoundingClientRect(),x=e.clientX-b.left,y=e.clientY-b.top,old=state.zoom;state.zoom=Math.max(.15,Math.min(4,old*Math.exp(-e.deltaY*.0015)));state.pan={x:x-(x-state.pan.x)*state.zoom/old,y:y-(y-state.pan.y)*state.zoom/old};render();},{passive:false});
for(const target of [$('#viewport'),$('.ps-resources')]){target.addEventListener('dragover',e=>{if(e.dataTransfer.types.includes('Files'))e.preventDefault();});target.addEventListener('drop',async e=>{if(!e.dataTransfer.files.length)return;e.preventDefault();for(const f of [...e.dataTransfer.files].slice(0,10))try{await uploadFile(f);}catch(ex){error(ex);}});}
window.addEventListener('keydown',async e=>{
 if($('#modal').open)return;
 if(e.target.closest('input,textarea,select,[contenteditable=true]')){if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='s'){e.preventDefault();try{await save();}catch(ex){error(ex);}}return;}
 const mod=e.ctrlKey||e.metaKey,key=e.key.toLowerCase();
 try{
  if(e.code==='Space'){space=true;e.preventDefault();}
  if(mod&&['s','z','y','d','c','v'].includes(key)){e.preventDefault();if(key==='s')await save();if(key==='z')actions[e.shiftKey?'redo':'undo']();if(key==='y')actions.redo();if(key==='d')actions.duplicate();if(key==='c')state.clipboard=selected().map(clone);if(key==='v'){editable();const ls=duplicateMany(state.clipboard);layers().push(...ls);repairConstructions(layers());state.selected=ls.map(l=>l.id);changed();}}
  if(e.key==='Delete'||e.key==='Backspace'){e.preventDefault();actions.delete();}if(e.key==='Escape'){state.selected=[];render();}
  if(e.key.startsWith('Arrow')){editable();e.preventDefault();const n=e.shiftKey?10:1;for(const l of selected().filter(l=>!l.locked)){if(e.key==='ArrowLeft')l.x-=n;if(e.key==='ArrowRight')l.x+=n;if(e.key==='ArrowUp')l.y-=n;if(e.key==='ArrowDown')l.y+=n;}changed();}
 }catch(ex){error(ex);}
});
window.addEventListener('keyup',e=>{if(e.code==='Space')space=false;});window.addEventListener('blur',()=>{space=false;endDrag();});
window.addEventListener('beforeunload',e=>{if(state.dirty){e.preventDefault();e.returnValue='';}});
new ResizeObserver(()=>drawRulers()).observe($('#viewport'));
(async()=>{
 render();renderResources();fit();
 try{await refreshAssets();state.members=(await json('/variables')).members;}catch(e){error(e);}
 let draft;try{draft=JSON.parse(localStorage.getItem('athena.patch.recovery.'+root.dataset.recovery)||'null');}catch{}
 if(draft&&confirm('Récupérer le projet interrompu le '+new Date(draft.at).toLocaleString('fr')+' ?')){
  try{state.doc=draft.document;state.project=draft.project;state.variant=draft.variant;await loadResources();history.reset(state.doc);state.dirty=true;$('#project-name').value=state.project?.name||'Projet récupéré';render();fit();notify('Brouillon récupéré. Enregistrez pour vérifier les éventuels conflits.');}catch(e){error(e);}
 }else await library();
})().catch(error);
// Exposed only as an immutable test/inspection facade; no authentication state.
export const studio={state,actions,raster,render,history};
