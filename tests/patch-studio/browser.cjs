// Run against the isolated PHP router: PATCH_TEST_URL=http://127.0.0.1:8765 node tests/patch-studio/browser.cjs
const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 const page=await browser.newPage({viewport:{width:1440,height:1000}});const errors=[],modules=[];page.on('response',r=>{if(/patch-studio(?:-core)?\.m?js(?:\?|$)/.test(r.url()))modules.push({url:r.url(),mime:r.headers()['content-type']});});page.on('pageerror',e=>errors.push(e.message));page.on('dialog',d=>d.accept());
 const url=process.env.PATCH_TEST_URL||'http://127.0.0.1:8765';
 try{
  await page.goto(url+'/patch-studio');await page.waitForSelector('#modal[open]');assert.equal(modules.length,2);for(const m of modules){assert.ok(/\.js\?v=/.test(m.url),m.url);assert.match(m.mime,/javascript/);}assert.equal(new URL(modules[0].url).search,new URL(modules[1].url).search);await page.click('[data-action="close-modal"]');
  const studio=()=>page.evaluate(()=>import(document.querySelector('[data-patch-studio-module]').src).then(m=>({doc:m.studio.state.doc,project:m.studio.state.project,selected:m.studio.state.selected})));
  await page.click('[data-tab="text"]');await page.click('[data-action="add-text"]');
  await page.fill('[data-prop="content"]','SABER {callsign}');await page.locator('[data-prop="content"]').blur();
  await page.selectOption('[data-prop="fontFamily"]','Oswald');await page.fill('[data-prop="letterSpacing"]','7');await page.locator('[data-prop="letterSpacing"]').blur();
  await page.fill('[data-prop="rotation"]','15');await page.locator('[data-prop="rotation"]').blur();
  await page.click('[data-tab="patterns"]');await page.click('[data-pattern="multicam"]');
  let s=await studio();const l=s.doc.variants.standard.at(-1);assert.equal(l.fill.pattern,'multicam');assert.equal(l.fontFamily,'Oswald');assert.equal(l.letterSpacing,7);assert.equal(l.rotation,15);
  await page.click('[data-action="duplicate"]');s=await studio();assert.equal(s.doc.variants.standard.length,4);
  await page.click('[data-action="undo"]');s=await studio();assert.equal(s.doc.variants.standard.length,3);await page.click('[data-action="redo"]');
  // Import actual image through PHP upload/validation/private serving.
  const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAEAAAAAgCAIAAAAt/+nTAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAQ0lEQVRYhe3PQQ3AIADAQMASWtCC/YngcVnSU9DOc/f4s6UDXjWgNaA1oDWgNaA1oDWgNaA1oDWgNaA1oDWgNaA1oH0juQE/pnC6FwAAAABJRU5ErkJggg==','base64');
  await page.setInputFiles('#asset-file',{name:'test.png',mimeType:'image/png',buffer:png});await page.waitForFunction(()=>document.querySelectorAll('[data-asset-add]').length>0);
  await page.waitForTimeout(200);s=await studio();assert.equal(s.doc.variants.standard.at(-1).type,'image');
  await page.click('[data-action="save"]');await page.waitForFunction(()=>document.querySelector('#save-state').textContent.includes('Enregistré'));
  s=await studio();assert.ok(s.project.id);const id=s.project.id;const saved=JSON.stringify(s.doc);
  // Verify persisted data through the actual endpoint, then reopen through UI.
  const fromApi=await page.evaluate(async id=>(await fetch('/api/patch-studio/projects/'+id)).json(),id);assert.equal(JSON.stringify(fromApi.project.document),saved);
  // Backend CSRF rejection and revision conflict.
  const csrf=await page.evaluate(async id=>(await fetch('/api/patch-studio/projects/'+id,{method:'PUT',headers:{'Content-Type':'application/json'},body:'{}'})).status,id);assert.equal(csrf,403);
  const conflict=await page.evaluate(async id=>(await fetch('/api/patch-studio/projects/'+id,{method:'PUT',headers:{'Content-Type':'application/json','X-CSRF-Token':'test-token'},body:JSON.stringify({revision:0})})).status,id);assert.equal(conflict,409);
  // Actual raster uses embedded local font files, SVG pattern text and alpha.
  const render=await page.evaluate(async()=>{const {studio}=await import(document.querySelector('[data-patch-studio-module]').src);const b=await studio.raster();return {data:b,length:b.length};});assert.ok(render.length>1000);
  fs.writeFileSync('/tmp/athena-patch-browser.png',Buffer.from(render.data,'base64'));
  for(const format of ['png','paa','pbo','zip','publish']){
   const result=await page.evaluate(async({id,png,format})=>{const p=(await(await fetch('/api/patch-studio/projects/'+id)).json()).project;const r=await fetch('/api/patch-studio/projects/'+id+'/export',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':'test-token'},body:JSON.stringify({revision:Number(p.revision),variant:'standard',format,png,letterbox:true})});const bytes=await r.arrayBuffer();return {status:r.status,body:r.status===200?null:new TextDecoder().decode(bytes),bytes:bytes.byteLength};},{id,png:render.data,format});assert.equal(result.status,200,format+JSON.stringify(result));assert.ok(result.bytes>0);
  }
  await page.click('[data-action="library"]');await page.waitForSelector('[data-open="'+id+'"]');await page.click('[data-open="'+id+'"]');
  assert.equal(JSON.stringify((await studio()).doc),saved);
  await page.screenshot({path:'/tmp/athena-patch-studio-ui.png',fullPage:true});
  for(const plan of ['free','standard','pro']){
   const denied=await page.request.get(url+'/api/patch-studio/projects',{headers:{'X-Test-Plan':plan}});assert.equal(denied.status(),403);assert.equal((await denied.json()).required_plan,'pro_plus');
   const locked=await page.request.get(url+'/patch-studio',{headers:{'X-Test-Plan':plan}});assert.equal(locked.status(),403);assert.ok((await locked.text()).includes('Une formule Pro+ minimum est requise'));
  }
  assert.ok(await page.locator('.ps-disclaimer').isVisible());

  assert.deepEqual(errors,[]);console.log('PASS browser: JS MIME/version/Pro+ gates/disclaimer/text/font/spacing/pattern/rotation/undo/redo/import/save/reload/CSRF/conflict/PNG/PAA/PBO/ZIP/publication');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
