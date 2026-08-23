export const formatPrice = (value:number) => new Intl.NumberFormat('en-US',{style:'currency',currency:'USD',maximumFractionDigits:0}).format(value)
export const discountPercent = (price:number, oldPrice:number|null) => oldPrice && oldPrice > price ? Math.round((1-price/oldPrice)*100) : 0
export const formatDate = (value:string) => new Intl.DateTimeFormat('en-US',{dateStyle:'medium'}).format(new Date(value))
export const clamp = (value:number,min:number,max:number) => Math.min(max,Math.max(min,value))
