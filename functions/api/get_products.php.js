/**
 * Cloudflare Pages Function
 * Handles: /api/get_products.php
 * Serves dynamic product catalog with search, category & sort filtering.
 */

import productsData from '../../data/products.json';

export async function onRequest(context) {
  const { request } = context;
  const url = new URL(request.url);

  const sort = url.searchParams.get('sort') || 'best_selling';
  const search = (url.searchParams.get('search') || '').toLowerCase().trim();
  const category = (url.searchParams.get('category') || '').trim();
  const favorite = url.searchParams.get('favorite');

  let list = [...productsData.data];

  // Filter by category
  if (category && category.toLowerCase() !== 'all') {
    list = list.filter(p => p.category.toLowerCase() === category.toLowerCase());
  }

  // Filter by search term
  if (search) {
    list = list.filter(p => 
      p.name.toLowerCase().includes(search) || 
      (p.description && p.description.toLowerCase().includes(search))
    );
  }

  // Filter by favorite
  if (favorite !== null && favorite !== '') {
    const isFav = parseInt(favorite, 10);
    list = list.filter(p => p.is_favorite === isFav);
  }

  // Sort
  if (sort === 'price_low_high') {
    list.sort((a, b) => {
      const pA = a.packages && a.packages[0] ? a.packages[0].price : 0;
      const pB = b.packages && b.packages[0] ? b.packages[0].price : 0;
      return pA - pB;
    });
  } else if (sort === 'price_high_low') {
    list.sort((a, b) => {
      const pA = a.packages && a.packages[0] ? a.packages[0].price : 0;
      const pB = b.packages && b.packages[0] ? b.packages[0].price : 0;
      return pB - pA;
    });
  } else if (sort === 'name_asc') {
    list.sort((a, b) => a.name.localeCompare(b.name));
  } else if (sort === 'name_desc') {
    list.sort((a, b) => b.name.localeCompare(a.name));
  }

  return new Response(JSON.stringify({
    status: 'success',
    count: list.length,
    data: list
  }), {
    headers: {
      'Content-Type': 'application/json; charset=utf-8',
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
      'Cache-Control': 'public, max-age=60'
    }
  });
}
