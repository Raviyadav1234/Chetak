import React from 'react';
import { QueryClient, QueryClientProvider, useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import axios from 'axios';
import './App.css';

const queryClient = new QueryClient();

const api = axios.create({
  baseURL: 'http://localhost:8000/api/v1',
  headers: {
    'Content-Type': 'application/json',
  },
});

interface Product {
  id: number;
  name: string;
  stock: number;
}

function ProductList() {
  const queryClient = useQueryClient();

  const { data: products, isLoading, isError } = useQuery<Product[]>({
    queryKey: ['products'],
    queryFn: async () => {
      const { data } = await api.get('/products');
      return data.data;
    },
  });

  const bookMutation = useMutation({
    mutationFn: async ({ id, quantity }: { id: number; quantity: number }) => {
      const { data } = await api.post(`/products/${id}/book`, { quantity });
      return data;
    },
    onMutate: async (newBooking) => {
      await queryClient.cancelQueries({ queryKey: ['products'] });
      const previousProducts = queryClient.getQueryData<Product[]>(['products']);
      
      queryClient.setQueryData<Product[]>(['products'], (old) => {
        if (!old) return old;
        return old.map(p => 
          p.id === newBooking.id 
            ? { ...p, stock: p.stock - newBooking.quantity }
            : p
        );
      });

      return { previousProducts };
    },
    onError: (err, newBooking, context) => {
      queryClient.setQueryData(['products'], context?.previousProducts);
      alert('Booking failed! ' + (err as any).response?.data?.message || err.message);
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['products'] });
    },
  });

  if (isLoading) return <div className="loading">Loading products...</div>;
  if (isError) return <div className="error">Failed to load products.</div>;

  return (
    <div className="product-container">
      <h1>Products</h1>
      <div className="product-list">
        {products?.map((product) => (
          <div key={product.id} className="product-card">
            <h2>{product.name}</h2>
            <p>Stock: {product.stock}</p>
            <button 
              onClick={() => bookMutation.mutate({ id: product.id, quantity: 1 })}
              disabled={product.stock <= 0 || bookMutation.isPending}
              className="book-btn"
            >
              {bookMutation.isPending ? 'Booking...' : 'Book 1 Unit'}
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <div className="app-main">
        <ProductList />
      </div>
    </QueryClientProvider>
  );
}

export default App;

