import test from 'node:test';
import assert from 'node:assert/strict';
import {adjustLevels,nightVision,legibility,nvSettings,clampNv,NV_RANGE} from '../../public/assets/js/patch-studio-night.js';
import core from './load-core.mjs';
const px=(...rgba)=>new Uint8ClampedArray(rgba);
test('night settings are clamped and default to neutral',()=>{
 assert.deepEqual(nvSettings(undefined),{gain:1,contrast:1});
 assert.deepEqual(nvSettings({nvGain:99,nvContrast:-4}),{gain:NV_RANGE.gain[1],contrast:NV_RANGE.contrast[0]});
 assert.equal(clampNv('abc',NV_RANGE.gain),1);
});
test('adjustLevels changes RGB only, keeps alpha and is a no-op at 1/1',()=>{
 const same=px(10,20,30,255);assert.deepEqual([...adjustLevels(same,{gain:1,contrast:1})],[10,20,30,255]);
 const lit=adjustLevels(px(100,100,100,128),{gain:1.5,contrast:1});assert.ok(lit[0]>100);assert.equal(lit[3],128);
 const clear=adjustLevels(px(5,5,5,0),{gain:2,contrast:1});assert.deepEqual([...clear],[5,5,5,0]);
 const high=adjustLevels(px(200,40,40,255),{gain:1,contrast:1.5});assert.ok(high[0]>200&&high[1]<40);
});
test('nightVision output is opaque green-dominant and deterministic',()=>{
 const a=px(200,200,200,255,10,10,10,255,0,0,0,0,255,255,255,255),b=a.slice();
 nightVision(a,2,2,{level:1});nightVision(b,2,2,{level:1});
 assert.deepEqual([...a],[...b]);
 for(let i=0;i<a.length;i+=4){assert.equal(a[i+3],255);assert.ok(a[i+1]>=a[i]&&a[i+1]>=a[i+2]);}
 assert.ok(a[1]>a[5],'a light pixel stays brighter than a dark one');
});
test('legibility tells readable, flat, dark and empty patches apart',()=>{
 const contrasted=new Uint8ClampedArray(400);for(let i=0;i<100;i++){const v=i%2?240:30;contrasted.set([v,v,v,255],i*4);}
 assert.equal(legibility(contrasted).verdict,'good');
 const flat=new Uint8ClampedArray(400);for(let i=0;i<100;i++)flat.set([120,120,120,255],i*4);
 assert.equal(legibility(flat).verdict,'flat');
 const dark=new Uint8ClampedArray(400);for(let i=0;i<100;i++)dark.set([8,8,8,255],i*4);
 assert.equal(legibility(dark).verdict,'dark');
 assert.equal(legibility(new Uint8ClampedArray(40)).verdict,'empty');
 assert.equal(legibility(dark,{level:3}).verdict==='dark',false,'a higher ambient level lifts a dark patch');
});
test('the IR template ships a readable night variant that is independent of day',()=>{
 const d=core.template('ir');assert.ok(Array.isArray(d.variants.night)&&d.variants.night.length===d.variants.standard.length);
 assert.notEqual(JSON.stringify(d.variants.night),JSON.stringify(d.variants.standard));
 assert.equal(core.template('name').variants.night,undefined);
});
