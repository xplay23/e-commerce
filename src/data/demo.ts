import type { Category, Product } from '@/types'
const now = new Date().toISOString()
export const demoCategories:Category[] = [
  ['audio','Audio'],['workspace','Workspace'],['living','Living'],['travel','Travel'],['wellness','Wellness']
].map(([slug,name],i)=>({id:`00000000-0000-4000-8000-00000000000${i+1}`,slug:slug!,name:name!,description:`Considered ${name!.toLowerCase()} essentials`,image_url:null,created_at:now}))
const raw = [
 ['Quiet Form Headphones','audio',349,399,18,'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=1000&q=85'],
 ['Aluminum Desk Lamp','workspace',189,null,9,'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=1000&q=85'],
 ['Arc Bluetooth Speaker','audio',229,269,14,'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=1000&q=85'],
 ['Hinoki Essential Set','wellness',78,null,25,'https://images.unsplash.com/photo-1603006905003-be475563bc59?auto=format&fit=crop&w=1000&q=85'],
 ['Everyday Carry Pack','travel',245,null,7,'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=1000&q=85'],
 ['Ripple Glass Carafe','living',96,120,20,'https://images.unsplash.com/photo-1603899122634-f086ca5f5ddd?auto=format&fit=crop&w=1000&q=85'],
 ['Contour Keyboard','workspace',159,null,12,'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=1000&q=85'],
 ['Field Travel Mug','travel',48,null,0,'https://images.unsplash.com/photo-1514228742587-6b1558fcca3d?auto=format&fit=crop&w=1000&q=85'],
 ['Linen Throw','living',135,160,6,'https://images.unsplash.com/photo-1583845112203-454c2254edc7?auto=format&fit=crop&w=1000&q=85'],
 ['Balance Diffuser','wellness',119,null,16,'https://images.unsplash.com/photo-1608571423902-eed4a5ad8108?auto=format&fit=crop&w=1000&q=85'],
 ['Studio Headphones','audio',279,null,8,'https://images.unsplash.com/photo-1484704849700-f032a568e944?auto=format&fit=crop&w=1000&q=85'],
 ['Stoneware Set','living',168,190,11,'https://images.unsplash.com/photo-1610701596007-11502861dcfa?auto=format&fit=crop&w=1000&q=85']
] as const
export const demoProducts:Product[] = raw.map((p,i)=>{const category=demoCategories.find(c=>c.slug===p[1])!; return {id:`10000000-0000-4000-8000-${String(i+1).padStart(12,'0')}`,category_id:category.id,name:p[0],slug:p[0].toLowerCase().replace(/ /g,'-'),description:`Designed with restraint and made to last. The ${p[0]} brings quiet function to your everyday rituals, using honest materials and thoughtful details.`,short_description:'Quietly functional. Intentionally made.',price:p[2],old_price:p[3],stock:p[4],image_url:p[5],is_active:true,is_featured:i<4,created_at:new Date(Date.now()-i*86400000).toISOString(),updated_at:now,category}})
