// Actual Chromium SVG, masks, text fonts, alpha, inline previews and high-resolution PNG.
const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 const page=await browser.newPage({viewport:{width:1600,height:1120}}),url=process.env.PATCH_TEST_URL||'http://127.0.0.1:8765',errors=[];
 page.on('pageerror',e=>errors.push(e.message));page.on('dialog',d=>d.accept());
 const bind=()=>page.evaluate(async()=>{const src=document.querySelector('[data-patch-studio-module]').src;globalThis.studio=(await import(src)).studio;globalThis.core=await import(new URL('./patch-studio-core.js'+new URL(src).search,src));});
 const evaluate=fn=>page.evaluate(fn=>Function('studio','core','return ('+fn+')(studio,core)')(globalThis.studio,globalThis.core),fn.toString());
 try{
  await page.goto(url+'/patch-studio');await page.waitForSelector('#studio-home #home-status[hidden]',{state:'attached'});await bind();await page.click('#home-templates [data-template="laser"]');await page.waitForFunction(()=>studio.state.doc.variants.standard[1]?.construction?.mode==='laser'&&document.querySelector('[data-construction="laser"]'));
  assert.equal(await page.locator('[data-fabrication-section]').count(),5);
  await page.click('#layers [data-layer-row]:last-child');
  assert.equal(await page.locator('[data-prop="stroke.mode"]').inputValue(),'same');
  await page.fill('[data-prop="stroke.density"]','1.8');await page.locator('[data-prop="stroke.density"]').blur();
  const count=await page.locator('#artwork [data-thread-count]').getAttribute('data-thread-count');assert.ok(Number(count)>1000);
  await page.click('#layers [data-layer-row]:first-child');assert.equal(await page.locator('[data-prop="construction.mode"]').inputValue(),'laser');
  // Input redraws before blur; grouping and undo remain real document operations.
  await page.fill('[data-prop="content"]','SABER 1-1');await page.waitForFunction(()=>studio.state.doc.variants.standard[1].content==='SABER 1-1'&&document.querySelector('#artwork').textContent.includes('SABER 1-1'));
  await page.locator('[data-prop="content"]').blur();
  await page.fill('[data-prop="construction.depth"]','1.8');await page.locator('[data-prop="construction.depth"]').blur();
  const native=await evaluate(s=>s.raster());fs.writeFileSync('/tmp/athena-laser-native.png',Buffer.from(native,'base64'));
  const high=await evaluate(s=>s.raster({width:4096,height:2048,background:'transparent'}));fs.writeFileSync('/tmp/athena-laser-4k.png',Buffer.from(high,'base64'));
  const light=await evaluate(s=>s.raster({width:2048,height:1024,background:'light'}));fs.writeFileSync('/tmp/athena-laser-product.png',Buffer.from(light,'base64'));
  await page.click('[data-action="save"]');await page.waitForFunction(()=>studio.state.project&&!studio.state.dirty);
  const saved=await evaluate(s=>({document:JSON.stringify(s.state.doc),id:s.state.project.id,revision:Number(s.state.project.revision)}));
  const result=await page.evaluate(async({id,revision,png})=>{const r=await fetch('/api/patch-studio/projects/'+id+'/export',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':'test-token'},body:JSON.stringify({revision,variant:'standard',format:'png',png})});return {status:r.status,size:(await r.arrayBuffer()).byteLength};},{...saved,png:high});assert.equal(result.status,200);assert.ok(result.size>10000);
  await page.click('[data-action="export"]');await page.selectOption('#export-size','2048x1024');await page.selectOption('#export-background','light');
  const [download]=await Promise.all([page.waitForEvent('download'),page.click('[data-action="run-export"]')]);await download.saveAs('/tmp/athena-fabrication-export-ui.png');assert.ok(fs.statSync('/tmp/athena-fabrication-export-ui.png').size>10000);await page.click('[data-action="close-modal"]');
  await page.screenshot({path:'/tmp/athena-fabrication-ui.png',fullPage:true});
  await page.reload();await page.waitForSelector('#studio-home #home-status[hidden]',{state:'attached'});await bind();await page.locator('[data-open="'+saved.id+'"]').first().click();await page.waitForFunction(id=>studio.state.project?.id===id&&!document.querySelector('#modal').open,saved.id);
  assert.equal(await evaluate(s=>JSON.stringify(s.state.doc)),saved.document);assert.equal(await evaluate(s=>s.raster()),native,'save/reopen has identical manufactured pixels');
  // Full square 4K textile render goes through the bounded backend PNG path, not only a client download.
  const square=await evaluate(async(s,c)=>{const old=s.state.doc,d=c.clone(old);s.state.doc=d;d.canvas.height=512;d.variants.standard[0].height=492;d.variants.standard[1].y=185;try{return {document:d,png:await s.raster({width:4096,height:4096})};}finally{s.state.doc=old;}});
  const squareResult=await page.evaluate(async({document,png})=>{const created=await fetch('/api/patch-studio/projects',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':'test-token'},body:JSON.stringify({name:'4K square textile test',document})});const {project}=await created.json();const r=await fetch('/api/patch-studio/projects/'+project.id+'/export',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':'test-token'},body:JSON.stringify({revision:Number(project.revision),format:'png',variant:'standard',png})});return {status:r.status,bytes:(await r.arrayBuffer()).byteLength};},square);assert.equal(squareResult.status,200);assert.ok(squareResult.bytes>10000);
  // Pixel-level continuity with a transformed, single image camouflage fill.
  const continuity=await evaluate(async(s,c)=>{
   const previous=s.state.doc,d=c.template('laser');s.state.doc=d;const [support,text]=d.variants.standard;
   text.visible=false;support.material.type='flat';support.stroke.finish='legacy';support.stroke.mode='same';Object.assign(support.fill,{rotation:26,offsetX:7,offsetY:-9,scale:1.2});d.render={...d.render,lightIntensity:0,relief:0,contactShadow:0};
   const bytes=async()=>{const im=new Image();im.src='data:image/png;base64,'+await s.raster();await im.decode();const out=document.createElement('canvas');out.width=512;out.height=256;const ctx=out.getContext('2d');ctx.drawImage(im,0,0);return ctx.getImageData(0,0,512,256).data;};
   const a=await bytes();support.stroke.width=0;const b=await bytes();let diff=0,total=0;
   // Exclude antialiasing at the outer contour. All interior pixels, including the seam join, must match.
   for(let y=45;y<211;y++)for(let x=14;x<498;x++){const i=(y*512+x)*4;if(a[i+3]===255&&b[i+3]===255){for(let k=0;k<3;k++)diff+=Math.abs(a[i+k]-b[i+k]);total+=3;}}
   s.state.doc=previous;return {diff,total};
  });assert.equal(continuity.diff,0,'no shifted or retiled camouflage at the border');
  const exterior=await evaluate(async(s,c)=>{const old=s.state.doc,d=c.template('laser');s.state.doc=d;d.variants.standard[0].stroke.position='outside';d.render.contactShadow=0;try{const im=new Image();im.src='data:image/png;base64,'+await s.raster();await im.decode();const cv=document.createElement('canvas');cv.width=512;cv.height=256;const cx=cv.getContext('2d');cx.drawImage(im,0,0);return cx.getImageData(6,128,1,1).data[3];}finally{s.state.doc=old;}});assert.ok(exterior>240,'laser mask preserves an exterior border');
  // A real cut must expose alpha when the lower layer is disabled, even with a support contact shadow.
  const alpha=await evaluate(async(s,c)=>{
   const previous=s.state.doc,d=c.template(),[support,text]=d.variants.standard;s.state.doc=d;
   Object.assign(support,{x:30,y:30,width:452,height:196,radius:0,shape:'rectangle',fill:c.solid('#8d5d35'),material:{type:'cordura500',relief:.8}});support.stroke.width=0;
   Object.assign(text,{content:'HH',x:115,y:55,width:280,height:145,fontSize:126,letterSpacing:10,fill:{type:'none'},material:{type:'flat'},construction:{mode:'laser',supportId:support.id,depth:0,sealWidth:0}});
   const png=await s.raster(),im=new Image();im.src='data:image/png;base64,'+png;await im.decode();const cv=document.createElement('canvas');cv.width=512;cv.height=256;const ctx=cv.getContext('2d');ctx.drawImage(im,0,0);const data=ctx.getImageData(0,0,512,256).data;let transparent=0;
   for(let y=70;y<190;y++)for(let x=130;x<380;x++)if(data[(y*512+x)*4+3]===0)transparent++;
   text.fill=c.solid('#294b88');const backing=await s.raster();s.state.doc=previous;return {transparent,png,backing};
  });assert.ok(alpha.transparent>1000,'laser glyphs are genuine transparent holes');fs.writeFileSync('/tmp/athena-laser-alpha.png',Buffer.from(alpha.png,'base64'));
  // Different weave pitches, loop fibres and satin stitch directions produce different high-resolution pixels.
  const materials=await evaluate(async(s,c)=>{
   const previous=s.state.doc,out=[];
   for(const type of ['cordura500','cordura1000','multicam','nylon','polyester','velcro','embroidery']){
    s.state.doc=c.template();const [support,text]=s.state.doc.variants.standard;support.fill=c.solid('#555d42');support.material.type=type;text.content=type.toUpperCase();text.fontSize=34;text.construction={mode:'embroidery',density:1.3,threadAngle:18};
    out.push({type,png:await s.raster({width:1024,height:512,background:'light'})});
   }
   s.state.doc=previous;return out;
  });assert.equal(new Set(materials.map(m=>m.png)).size,7);for(const m of materials)fs.writeFileSync('/tmp/athena-material-'+m.type+'.png',Buffer.from(m.png,'base64'));
  // Compare every weave using the same text as well (labels cannot create false positives).
  const hashes=await evaluate(async(s,c)=>{const previous=s.state.doc,out=[];for(const type of Object.keys(c.materials)){s.state.doc=c.template();s.state.doc.variants.standard[0].material.type=type;s.state.doc.variants.standard[1].visible=false;out.push(await s.raster());}s.state.doc=previous;return out;});assert.equal(new Set(hashes).size,8);
  const sheet=await browser.newPage({viewport:{width:1600,height:1120}});await sheet.setContent('<html><body style="margin:0;background:#e8e6df;display:grid;grid-template-columns:repeat(2,1fr)"></body></html>');
  await sheet.evaluate(images=>{for(const x of images){const im=new Image();im.src='data:image/png;base64,'+x.png;im.style.width='100%';document.body.append(im);}},[{type:'laser',png:light},...materials]);await sheet.waitForFunction(()=>[...document.images].every(i=>i.complete));await sheet.screenshot({path:'/tmp/athena-material-contact-sheet.png',fullPage:true});
  assert.deepEqual(errors,[]);console.log('PASS fabrication browser: live five-section UI/contour stitches/laser mask and alpha/texture continuity/4K PNG endpoint/material fibres/deterministic cold reopen');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
