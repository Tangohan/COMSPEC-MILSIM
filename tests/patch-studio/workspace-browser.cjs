const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
 const url=process.env.PATCH_TEST_URL||'http://127.0.0.1:8765',page=await browser.newPage({viewport:{width:1560,height:1100}}),errors=[];
 page.on('pageerror',e=>errors.push(e.message));page.on('dialog',d=>d.accept());
 const bind=()=>page.evaluate(async()=>globalThis.studio=(await import(document.querySelector('[data-patch-studio-module]').src)).studio);
 const state=()=>page.evaluate(()=>({project:studio.state.project,doc:studio.state.doc,selected:[...studio.state.selectedMembers]}));
 try{
  await page.goto(url+'/patch-studio');await page.waitForSelector('#studio-home #home-status[hidden]',{state:'attached'});await bind();assert.ok(await page.locator('#studio-home').isVisible());
  await page.click('#home-templates [data-template="laser"]');await page.waitForSelector('#studio-editor:not([hidden])');
  await page.fill('#project-name','SABER · création récente');await page.locator('#project-name').blur();await page.click('[data-action="save"]');await page.waitForFunction(()=>studio.state.project&&!studio.state.dirty);const original=await state();assert.equal(original.project.kind,'patch');
  await page.click('[data-action="library"]');await page.waitForSelector('[data-open="'+original.project.id+'"]');await page.waitForFunction(id=>{const im=document.querySelector('[data-open="'+id+'"] img');return im?.complete&&im.naturalWidth>0;},original.project.id);assert.ok(await page.locator('[data-open="'+original.project.id+'"] img').isVisible());
  const cover=await page.locator('[data-open="'+original.project.id+'"] img').getAttribute('src');assert.ok(cover.includes('/preview?v='));
  await page.screenshot({path:'/tmp/athena-patch-home.png',fullPage:true});await page.locator('[data-open="'+original.project.id+'"]').first().click();
  await page.click('[data-action="save-setup"]');await page.fill('#setup-name','SETUP · Cordura Laser');await page.selectOption('#setup-visibility','organization');await page.click('#setup-form button[type="submit"]');await page.waitForFunction(()=>!document.querySelector('#modal').open);
  assert.equal((await state()).project.id,original.project.id,'saving a setup preserves the open patch');
  await page.click('[data-action="library"]');await page.click('[data-home-filter="setups"]');await page.waitForSelector('#home-status[hidden]',{state:'attached'});const setupCard=page.locator('.ps-home-card').filter({hasText:'SETUP · Cordura Laser'}).first();await setupCard.locator('[data-use-setup]').first().click();
  await page.waitForFunction(()=>studio.state.project===null&&studio.state.doc.variants.standard[1].construction.mode==='laser'&&studio.state.view==='editor');assert.equal(JSON.stringify((await state()).doc),JSON.stringify(original.doc),'setup preserves complete layer construction');
  await page.click('[data-action="export"]');await page.waitForSelector('#export-preview-image[src]');assert.ok(await page.locator('.ps-export-layout').isVisible());await page.selectOption('#export-size','1024x512');await page.selectOption('#export-background','light');
  await page.click('#modal [data-action="members"]');await page.waitForSelector('[data-member-check="2"]');await page.fill('#member-query','TIGER');await page.waitForFunction(()=>document.querySelectorAll('[data-member-check]').length===1&&document.querySelector('[data-member-check="2"]'));
  await page.check('[data-member-check="2"]');await page.click('[data-workspace="apply-members"]');await page.waitForSelector('#export-format');assert.equal(await page.locator('#export-size').inputValue(),'1024x512');assert.equal(await page.locator('#export-background').inputValue(),'light');
  assert.match(await page.locator('#export-member-caption').textContent(),/SABER|TIGER/);assert.deepEqual((await state()).selected.sort(),[1,2]);
  await page.screenshot({path:'/tmp/athena-patch-export-dialog.png',fullPage:true});const [download]=await Promise.all([page.waitForEvent('download'),page.click('[data-action="run-export"]')]);await download.saveAs('/tmp/athena-workspace-export.png');assert.ok(fs.statSync('/tmp/athena-workspace-export.png').size>10000);
  const p=(await state()).project;assert.ok(p.id&&p.id!==original.project.id,'using a setup exports a new saved patch');
  const legacy=await page.evaluate(async document=>(await(await fetch('/api/patch-studio/projects',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':'test-token'},body:JSON.stringify({name:'Projet sans ancienne vignette',document})})).json()).project,(await state()).doc);
  await page.click('[data-action="close-modal"]');await page.click('[data-action="library"]');await page.waitForSelector('[data-open="'+legacy.id+'"]');await page.locator('[data-open="'+legacy.id+'"]').first().click();await page.waitForFunction(id=>studio.state.project?.id===id&&studio.state.project?.has_preview,legacy.id);const generated=await page.request.get(url+'/api/patch-studio/projects/'+legacy.id+'/preview');assert.equal(generated.status(),200,'old projects receive a real cover on opening');
  const denied=await page.request.get(url+'/api/patch-studio/variables',{headers:{'X-Test-RH':'no'}});assert.deepEqual((await denied.json()).members.map(m=>m.userId),[1]);
  const unauthorized=await page.request.get(url+'/api/patch-studio/projects/'+p.id+'/preview',{headers:{'X-Test-Plan':'free'}});assert.equal(unauthorized.status(),403);
  const csrf=await page.request.post(url+'/api/patch-studio/projects/'+p.id+'/preview',{data:{revision:p.revision,png:''}});assert.equal(csrf.status(),403);
  for(const endpoint of ['projects','assets']){const missing=await page.request.get(url+'/api/patch-studio/'+endpoint,{headers:{'X-Test-Schema':'missing'}});assert.equal(missing.status(),503);const message=await missing.json();assert.equal(message.code,'patch_studio_migration_required');assert.equal(message.migration,'patch_studio_workspace');assert.ok(!message.error.includes('SQLSTATE'));}
  assert.deepEqual(errors,[]);console.log('PASS workspace browser: recent home/private thumbnails/setup save and reuse/dynamic member search and selection/export modal/download/migration diagnostics/Pro+/CSRF');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
