import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync,writeFileSync} from 'node:fs';
import core from './load-core.mjs';
const {template,layer,compose,duplicateMany,group,ungroup,repairConstructions,materials}=core;
const catalog=JSON.parse(readFileSync(new URL('../../public/assets/textures/patch-studio/catalog.json',import.meta.url))),t=catalog.textures[0];
const textures={[t.id]:{...t,src:'data:image/jpeg;base64,'+readFileSync(new URL('../../public/assets/textures/patch-studio/'+t.file,import.meta.url)).toString('base64')}};
const laser=()=>template('laser');
test('laser is a support mask and backing, not a painted text effect',()=>{
 const d=laser(),[support,text]=d.variants.standard,s=compose(d,{textures});
 assert.match(s,/<mask[^>]+mask-type="luminance"/);assert.match(s,/data-backing="true"/);assert.match(s,/data-construction="laser"/);assert.match(s,/data-surface="velcro"/);assert.match(s,/operator="out"/);assert.equal(text.construction.supportId,support.id);
 const second=core.layer('text',{construction:{mode:'laser',supportId:support.id},content:'SECOND'});d.variants.standard.unshift(second);assert.equal((compose(d,{textures,selection:[text.id]}).match(/data-construction="laser"/g)||[]).length,2);d.variants.standard.shift();
 assert.equal(s,compose(JSON.parse(JSON.stringify(d)),{textures}));writeFileSync('/tmp/patch-fabrication.json',JSON.stringify(d));
});
test('same border reuses the support paint server including transforms',()=>{
 const d=laser(),support=d.variants.standard[0];support.fill.rotation=23;support.fill.offsetX=18;support.fill.scale=1.3;
 const s=compose(d,{textures});const paint=s.match(/<pattern id="([^"]+)"[^>]*viewBox="/)[1];
 assert.match(s,new RegExp(`stroke="url\\(#${paint}\\)"`));assert.match(s,new RegExp(`fill="url\\(#${paint}\\)"`));assert.equal((s.match(/<image href="data:image\/jpeg/g)||[]).length,1);
});
test('all shapes provide bounded contour stitches, at a controlled density',async()=>{
 const {contourSamples,relativeMatrix}=await import('data:text/javascript;base64,'+readFileSync(new URL('../../public/assets/js/patch-studio-surface.js',import.meta.url)).toString('base64'));
 for(const shape of core.shapes){const l=layer('shape',{shape});const samples=contourSamples(l,3,100);assert.ok(samples.length<=100&&samples.length>=12);for(const p of samples){assert.ok(Object.values(p).every(Number.isFinite));assert.ok(Math.abs(Math.hypot(p.nx,p.ny)-1)<1e-8);assert.ok(Math.abs(p.nx*p.tx+p.ny*p.ty)<1e-8);}}
 const l=layer('shape',{x:13,y:41,rotation:28,scaleX:1.7,scaleY:.6});assert.deepEqual(relativeMatrix(l,l),[1,0,0,1,0,0]);
 const d=template();d.variants.standard[0].stroke.finish='embroidered';const a=compose(d);d.variants.standard[0].stroke.density=2;const b=compose(d);assert.ok(+b.match(/data-thread-count="(\d+)"/)[1]>+a.match(/data-thread-count="(\d+)"/)[1]);
});
test('fabrication references survive duplication, grouping and deletion',()=>{
 const d=laser(),a=d.variants.standard,copy=duplicateMany(a);assert.equal(copy[1].construction.supportId,copy[0].id);assert.notEqual(copy[0].id,a[0].id);
 const other=layer('shape');a.push(other);assert.throws(()=>group(a,[a[1].id,other.id]),/découpe laser/);
 const g=group(a,[a[0].id,a[1].id]),c=duplicateMany([g])[0];assert.equal(c.children[1].construction.supportId,c.children[0].id);assert.ok(ungroup(a,g.id));
 const support=a.findIndex(l=>l.id===a.find(l=>l.type==='text').construction.supportId);a.splice(support,1);repairConstructions(a);assert.equal(a.find(l=>l.type==='text').construction.mode,'print');
});
test('materials and manufacturing modes are distinct and render settings change the pixels source',()=>{
 const d=template(),l=d.variants.standard[0],renders=new Set();
 for(const type of Object.keys(materials)){l.material.type=type;renders.add(compose(d));}assert.equal(renders.size,Object.keys(materials).length);
 for(const finish of ['embroidered','overlock','heatcut','none']){l.stroke.finish=finish;const svg=compose(d);assert.equal(svg.includes('data-thread-count'),['embroidered','overlock'].includes(finish));}
 d.variants.standard[1].construction={mode:'embroidery',density:1.5,threadAngle:40};assert.match(compose(d),/data-construction="embroidery"/);
 const before=compose(d);d.render.lightAngle=60;assert.notEqual(compose(d),before);
});
test('high-resolution SVG retains document viewBox and unique inline definition namespaces',()=>{
 const d=template(),s=compose(d,{pixelWidth:4096,pixelHeight:2048,background:'transparent',idPrefix:'export-'});
 assert.match(s,/width="4096" height="2048" viewBox="0 0 512 256"/);assert.match(s,/id="export-1"/);assert.ok(!s.includes('id="ps1"'));
 assert.match(compose(d,{background:'light'}),/fill="#e8e6df"/);assert.match(compose(d,{selection:[d.variants.standard[1].id]}),/data-layer=/);
 const cut=laser(),only=compose(cut,{textures,selection:[cut.variants.standard[1].id]});assert.ok(only.includes('data-layer="'+cut.variants.standard[0].id+'"'));
});

test('nested selection export retains group transforms, masks and laser dependencies',()=>{
 const d=laser(),a=d.variants.standard,g=group(a,a.map(l=>l.id));g.rotation=12;g.mask='ellipse';
 const selected=compose(d,{textures,selection:[g.children[1].id]});assert.ok(selected.includes('data-layer="'+g.id+'"'));assert.ok(selected.includes('data-layer="'+g.children[0].id+'"'));assert.match(selected,/rotate\(12/);assert.match(selected,/<ellipse/);
});
