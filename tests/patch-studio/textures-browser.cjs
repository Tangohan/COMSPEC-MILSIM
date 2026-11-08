const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 const page=require('./reveal.cjs')(await browser.newPage({viewport:{width:1500,height:1050}})),url=process.env.PATCH_TEST_URL||'http://127.0.0.1:8765',errors=[],external=[];
 page.on('pageerror',e=>errors.push(e.message));page.on('dialog',d=>d.accept());page.on('request',r=>{if(r.url().startsWith('http')&&new URL(r.url()).origin!==new URL(url).origin)external.push(r.url());});
 const bind=()=>page.evaluate(async()=>{const {studio}=await import(document.querySelector('[data-patch-studio-module]').src);globalThis.__patchStudioTest=studio;});
 const waitTexture=id=>page.waitForFunction(id=>globalThis.__patchStudioTest.state.doc.variants.standard.at(-1)?.fill.textureId===id,id);
 const evaluate=fn=>page.evaluate(fn=>Function('studio','return ('+fn+')(studio)')(globalThis.__patchStudioTest),fn.toString());
 try{
  await page.goto(url+'/patch-studio');await page.waitForSelector('#studio-home #home-status[hidden]',{state:'attached'});await bind();await page.click('#home-templates [data-template="name"]');await page.click('#studio-editor [data-action="new"]');
  await page.click('[data-tab="textures"]');await page.waitForSelector('[data-texture="multicam-original"]');assert.equal(await page.locator('[data-texture]').count(),5);
  await page.click('[data-texture="multicam-original"]');await waitTexture('multicam-original');assert.equal(await evaluate(s=>s.state.doc.variants.standard[0].fill.textureId),'multicam-original');
  await page.click('[data-tab="text"]');await page.click('[data-action="add-text"]');await page.fill('[data-prop="content"]','ATHENA');await page.locator('[data-prop="content"]').blur();
  await page.click('[data-tab="textures"]');
  const rasters=[];
  for(const variant of ['original','arid','tropic','alpine','black']){
   await page.click('[data-texture="multicam-'+variant+'"]');await waitTexture('multicam-'+variant);assert.equal(await evaluate(s=>s.state.doc.variants.standard.at(-1).fill.textureId),'multicam-'+variant);
   const raster=await evaluate(s=>s.raster());assert.ok(raster.length>1000);rasters.push(raster);
  }
  assert.equal(new Set(rasters).size,5,'five distinct actual textured text renders');
  for(const [key,value]of [['scale','.5'],['rotation','30'],['offsetX','12'],['offsetY','24'],['opacity','.8'],['brightness','1.1'],['contrast','1.2']]){await page.fill('[data-prop="fill.'+key+'"]',value);await page.locator('[data-prop="fill.'+key+'"]').blur();}
  await page.click('[data-action="save"]');await page.waitForFunction(()=>document.querySelector('#save-state').textContent.includes('Enregistré'));
  const project=await evaluate(s=>s.state.project),saved=await evaluate(s=>JSON.stringify(s.state.doc));assert.ok(!saved.includes('base64'));
  const png=await evaluate(s=>s.raster());fs.writeFileSync('/tmp/athena-textures-render.png',Buffer.from(png,'base64'));
  for(const format of ['png','paa','zip','pbo']){
   const result=await page.evaluate(async({id,revision,png,format})=>{const r=await fetch('/api/patch-studio/projects/'+id+'/export',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':'test-token'},body:JSON.stringify({revision,variant:'standard',format,png,letterbox:true})});return {status:r.status,bytes:(await r.arrayBuffer()).byteLength};},{id:project.id,revision:Number(project.revision),png,format});assert.equal(result.status,200,format);assert.ok(result.bytes>1000);
  }
  await page.reload();await page.waitForSelector('#studio-home #home-status[hidden]',{state:'attached'});await bind();await page.locator('[data-open="'+project.id+'"]').first().click();await page.waitForFunction(id=>globalThis.__patchStudioTest.state.project?.id===id&&!document.querySelector('#modal').open&&Object.keys(globalThis.__patchStudioTest.state.textureResources).length===2,project.id);assert.equal(await evaluate(s=>JSON.stringify(s.state.doc)),saved);
  assert.equal(await evaluate(s=>Object.keys(s.state.textureResources).length),2,'only project textures loaded on cold reopen');
  const reopened=await evaluate(s=>s.raster());assert.equal(reopened,png,'reopened render is identical');
  await page.click('[data-tab="textures"]');await page.waitForFunction(()=>document.querySelector('[data-tab="textures"]').getAttribute('aria-selected')==='true'&&[...document.querySelectorAll('[data-texture] img')].length===5&&[...document.querySelectorAll('[data-texture] img')].every(im=>im.complete&&im.naturalWidth>0));await page.screenshot({path:'/tmp/athena-real-textures-ui.png',fullPage:true});assert.deepEqual(errors,[]);assert.deepEqual(external,[]);
  console.log('PASS textures browser: five JPEGs/background/text/transforms/PNG/PAA/ZIP/PBO/save/cold reload/identical pixels/no external requests');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
