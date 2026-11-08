// Vision nocturne : calculs purs sur des pixels RGBA (Uint8ClampedArray), sans DOM, testables sous Node.
// REEL : adjustLevels() modifie les pixels du fichier exporte (PNG, PAA, PBO) pour la version nuit.
// APERCU : nightVision() et legibility() ne servent qu'a l'ecran ; ils ne changent jamais un fichier exporte.
export const NV_LEVELS={moonless:{label:'Nuit noire',level:.6},starlight:{label:'Nuit étoilée',level:1},moon:{label:'Clair de lune',level:1.5}};
export const NV_RANGE={gain:[.5,3],contrast:[.5,2]};
export const clampNv=(value,[min,max],fallback=1)=>{const n=Number(value);return Number.isFinite(n)?Math.min(max,Math.max(min,n)):fallback;};
export const nvSettings=render=>({gain:clampNv(render?.nvGain,NV_RANGE.gain),contrast:clampNv(render?.nvContrast,NV_RANGE.contrast)});
const luma=(r,g,b)=>(.2126*r+.7152*g+.0722*b)/255;
// Luminosite telle qu'un amplificateur de lumiere la restitue : forte pente dans les ombres.
const amplify=(y,level)=>Math.pow(Math.min(1,Math.max(0,y*level*1.6)),.8);
/** Applique un gain et un contraste aux canaux RGB ; l'alpha est conserve. Modifie le tableau et le renvoie. */
export function adjustLevels(data,{gain=1,contrast=1}={}){
 if(gain===1&&contrast===1)return data;
 for(let i=0;i<data.length;i+=4){
  if(data[i+3]===0)continue;
  for(let c=0;c<3;c++)data[i+c]=Math.round(Math.min(1,Math.max(0,((data[i+c]/255-.5)*contrast+.5)*gain))*255);
 }
 return data;
}
/** Simulation d'ecran de JVN (vert monochrome, grain, vignette). Apercu uniquement, jamais exporte. */
export function nightVision(data,width,height,{level=1,grain=.05}={}){
 let seed=1234567;const rnd=()=>{seed=(seed*1664525+1013904223)>>>0;return seed/4294967296;};
 const cx=width/2,cy=height/2,norm=cx*cx+cy*cy||1,ground=amplify(.07,level);
 for(let p=0,i=0;i<data.length;i+=4,p++){
  const a=data[i+3]/255,y=a*amplify(luma(data[i],data[i+1],data[i+2]),level)+(1-a)*ground;
  const x=p%width,row=(p-x)/width,d=((x-cx)**2+(row-cy)**2)/norm;
  const v=Math.min(1,Math.max(0,(y+(rnd()-.5)*grain)*(1-.4*d)));
  data[i]=Math.round(v*95);data[i+1]=Math.round(v*255);data[i+2]=Math.round(v*110);data[i+3]=255;
 }
 return data;
}
/** Lisibilite sous amplification : ecart de luminosite entre les zones claires et sombres du motif. */
export function legibility(data,{level=1}={}){
 const bins=new Array(64).fill(0);let n=0;
 for(let i=0;i<data.length;i+=4){if(data[i+3]<200)continue;bins[Math.min(63,Math.floor(amplify(luma(data[i],data[i+1],data[i+2]),level)*64))]++;n++;}
 if(!n)return {verdict:'empty',spread:0,median:0};
 const at=q=>{let sum=0;for(let b=0;b<64;b++){sum+=bins[b];if(sum>=n*q)return (b+.5)/64;}return 1;};
 const p10=at(.1),p90=at(.9),median=at(.5),spread=p90-p10;
 return {verdict:median<.18?'dark':median>.9?'bright':spread<.22?'flat':'good',spread,median,p10,p90};
}
