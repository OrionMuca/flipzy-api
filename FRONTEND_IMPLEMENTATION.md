# Flipzy Frontend Implementation Guide

**Backend API Version:** 1.0.0  
**Base URL:** `/api/v1`  
**Documentation:** `/api/documentation` (Swagger UI)

---

## 🚀 Getting Started

### **1. Base Configuration**

```javascript
// config/api.js
const API_BASE_URL = 'http://your-domain.com/api/v1';
const API_DOCS_URL = 'http://your-domain.com/api/documentation';
```

### **2. Authentication Setup**

```javascript
// services/auth.js
import axios from 'axios';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Add token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('access_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle token refresh on 401
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // Redirect to login
      localStorage.removeItem('access_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);
```

---

## 🔐 Authentication Flow

### **Register User**
```javascript
const register = async (userData) => {
  const response = await api.post('/register', {
    name: userData.name,
    email: userData.email,
    password: userData.password,
    password_confirmation: userData.password,
    role: userData.role, // 'investor' or 'wholesaler'
  });
  
  const { access_token, user } = response.data.data;
  localStorage.setItem('access_token', access_token);
  return user;
};
```

### **Login**
```javascript
const login = async (email, password) => {
  const response = await api.post('/login', { email, password });
  const { access_token, user } = response.data.data;
  localStorage.setItem('access_token', access_token);
  return user;
};
```

### **Get Current User**
```javascript
const getCurrentUser = async () => {
  const response = await api.get('/user');
  return response.data.data.user;
};
```

### **Logout**
```javascript
const logout = async () => {
  await api.post('/logout');
  localStorage.removeItem('access_token');
};
```

---

## 🏠 Properties

### **List Properties (Public)**
```javascript
const getProperties = async (filters = {}) => {
  const params = new URLSearchParams(filters);
  const response = await api.get(`/properties?${params}`);
  return response.data.data; // Paginated collection
};

// Usage:
const properties = await getProperties({
  city: 'Denver',
  state: 'CO',
  min_price: 100000,
  max_price: 500000,
  bedrooms: 3,
  per_page: 20,
});
```

### **Get Property Details**
```javascript
const getProperty = async (propertyId) => {
  const response = await api.get(`/properties/${propertyId}`);
  return response.data.data;
};
```

### **Create Property (Wholesaler)**
```javascript
const createProperty = async (propertyData, images = []) => {
  const formData = new FormData();
  
  // Add property fields
  Object.keys(propertyData).forEach(key => {
    formData.append(key, propertyData[key]);
  });
  
  // Add images
  images.forEach((image, index) => {
    formData.append(`images[${index}]`, image);
  });
  
  formData.append('primary_image_index', 0);
  
  const response = await api.post('/properties', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  
  return response.data.data;
};
```

### **Update Property**
```javascript
const updateProperty = async (propertyId, updates) => {
  const formData = new FormData();
  Object.keys(updates).forEach(key => {
    formData.append(key, updates[key]);
  });
  
  const response = await api.post(`/properties/${propertyId}`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  
  return response.data.data;
};
```

### **Delete Property**
```javascript
const deleteProperty = async (propertyId) => {
  await api.delete(`/properties/${propertyId}`);
};
```

---

## 💬 Messaging

### **List Conversations**
```javascript
const getConversations = async () => {
  const response = await api.get('/conversations');
  return response.data.data;
  // Returns: [{ id, property, other_participant, last_message, unread_count, ... }]
};
```

### **Get Conversation**
```javascript
const getConversation = async (conversationId) => {
  const response = await api.get(`/conversations/${conversationId}`);
  return response.data.data;
};
```

### **Create Conversation**
```javascript
const createConversation = async (propertyId, participantId) => {
  const response = await api.post('/conversations', {
    property_id: propertyId,
    participant_two_id: participantId,
  });
  return response.data.data;
};
```

### **Get Messages**
```javascript
const getMessages = async (conversationId) => {
  const response = await api.get(`/conversations/${conversationId}/messages`);
  return response.data.data;
};
```

### **Send Message**
```javascript
const sendMessage = async (conversationId, body) => {
  const response = await api.post(`/conversations/${conversationId}/messages`, {
    body,
  });
  return response.data.data;
};
```

### **Mark Messages as Read**
```javascript
const markAsRead = async (conversationId) => {
  await api.put(`/conversations/${conversationId}/messages/read`);
};
```

### **Get Unread Count**
```javascript
const getUnreadCount = async () => {
  const response = await api.get('/messages/unread-count');
  return response.data.data.unread_count;
};
```

---

## 📊 Real-time Messaging Setup

### **Install Pusher/Soketi Client**
```bash
npm install pusher-js
# or for Soketi
npm install @soketi/soketi-js
```

### **Configure Broadcasting**
```javascript
import Pusher from 'pusher-js';

const pusher = new Pusher(process.env.VITE_PUSHER_APP_KEY, {
  cluster: process.env.VITE_PUSHER_APP_CLUSTER,
  encrypted: true,
  authEndpoint: '/api/broadcasting/auth',
  auth: {
    headers: {
      Authorization: `Bearer ${localStorage.getItem('access_token')}`,
    },
  },
});
```

### **Subscribe to Conversation Channel**
```javascript
const subscribeToConversation = (conversationId, callbacks) => {
  const channel = pusher.subscribe(`private-conversation.${conversationId}`);
  
  channel.bind('message.sent', (data) => {
    callbacks.onMessageSent?.(data);
  });
  
  channel.bind('message.read', (data) => {
    callbacks.onMessageRead?.(data);
  });
  
  return () => channel.unsubscribe();
};
```

### **Example Usage**
```javascript
// In your chat component
useEffect(() => {
  const unsubscribe = subscribeToConversation(conversationId, {
    onMessageSent: (data) => {
      setMessages(prev => [...prev, data]);
    },
    onMessageRead: (data) => {
      setMessages(prev => prev.map(msg => 
        msg.id === data.message_id 
          ? { ...msg, is_read: true, read_at: data.read_at }
          : msg
      ));
    },
  });
  
  return unsubscribe;
}, [conversationId]);
```

---

## 📈 Analytics

### **Track Property View**
```javascript
const trackView = async (propertyId) => {
  await api.post(`/properties/${propertyId}/view`);
};
```

### **Track Property Save**
```javascript
const trackSave = async (propertyId, notes = null) => {
  await api.post(`/properties/${propertyId}/save`, { notes });
};
```

### **Track Property Inquiry**
```javascript
const trackInquiry = async (propertyId, message) => {
  await api.post(`/properties/${propertyId}/inquiry`, { message });
};
```

### **Get Property Analytics**
```javascript
const getPropertyAnalytics = async (propertyId, days = 30) => {
  const response = await api.get(`/properties/${propertyId}/analytics?days=${days}`);
  return response.data.data;
  // Returns: { total_views, unique_views, total_saves, ... }
};
```

### **Get Credibility Score**
```javascript
const getCredibilityScore = async (userId, days = 90) => {
  const response = await api.get(`/users/${userId}/credibility?days=${days}`);
  return response.data.data;
  // Returns: { score, normalized_score, breakdown, ... }
};
```

---

## 🤖 AI Rehab Estimation

### **Generate Estimate (Premium/VIP/Admin)**
```javascript
const generateEstimate = async (propertyId, options = {}) => {
  const params = new URLSearchParams(options);
  const response = await api.post(`/properties/${propertyId}/estimate?${params}`);
  return response.data.data;
  // Returns: { id, estimated_cost, breakdown, timeline_weeks, risk_factors, ... }
};

// Usage:
const estimate = await generateEstimate(propertyId, {
  force_refresh: false,
  model: 'gpt-3.5-turbo',
});
```

### **Get Estimate History**
```javascript
const getEstimateHistory = async (propertyId, limit = 10) => {
  const response = await api.get(`/properties/${propertyId}/estimates?limit=${limit}`);
  return response.data.data;
};
```

---

## 👨‍💼 Admin Endpoints

### **Check if User is Admin**
```javascript
const isAdmin = (user) => {
  return user?.roles?.some(role => role.name === 'admin');
};
```

### **Get System Overview**
```javascript
const getSystemOverview = async () => {
  const response = await api.get('/admin/analytics/overview');
  return response.data.data;
  // Returns: { users, properties, engagement, subscriptions, messaging }
};
```

### **Get Trends**
```javascript
const getTrends = async (days = 30) => {
  const response = await api.get(`/admin/analytics/trends?days=${days}`);
  return response.data.data;
  // Returns: { dates, users: { new_users }, properties: { new_properties }, engagement: { views, saves, inquiries } }
};
```

### **List Users**
```javascript
const getUsers = async (filters = {}) => {
  const params = new URLSearchParams(filters);
  const response = await api.get(`/admin/users?${params}`);
  return response.data;
  // Returns: { data: [...], meta: { current_page, total, ... } }
};
```

### **Update User**
```javascript
const updateUser = async (userId, updates) => {
  const response = await api.put(`/admin/users/${userId}`, updates);
  return response.data.data;
};
```

---

## 🎨 UI Components Examples

### **Property Card Component**
```jsx
const PropertyCard = ({ property }) => {
  const handleView = () => {
    trackView(property.id);
    navigate(`/properties/${property.id}`);
  };
  
  return (
    <div onClick={handleView}>
      <img src={property.primary_image?.url} alt={property.title} />
      <h3>{property.title}</h3>
      <p>{property.address}, {property.city}, {property.state}</p>
      <p>${property.financial.asking_price.toLocaleString()}</p>
      <p>{property.details.bedrooms} bed, {property.details.bathrooms} bath</p>
    </div>
  );
};
```

### **Chat Component**
```jsx
const ChatComponent = ({ conversationId }) => {
  const [messages, setMessages] = useState([]);
  const [newMessage, setNewMessage] = useState('');
  
  useEffect(() => {
    loadMessages();
    const unsubscribe = subscribeToConversation(conversationId, {
      onMessageSent: (data) => setMessages(prev => [...prev, data]),
    });
    return unsubscribe;
  }, [conversationId]);
  
  const handleSend = async () => {
    await sendMessage(conversationId, newMessage);
    setNewMessage('');
  };
  
  return (
    <div>
      {messages.map(msg => (
        <div key={msg.id}>
          <strong>{msg.sender.name}:</strong> {msg.body}
        </div>
      ))}
      <input value={newMessage} onChange={e => setNewMessage(e.target.value)} />
      <button onClick={handleSend}>Send</button>
    </div>
  );
};
```

---

## 🔄 Error Handling

```javascript
const handleApiError = (error) => {
  if (error.response) {
    // Server responded with error
    const { status, data } = error.response;
    
    switch (status) {
      case 401:
        // Unauthorized - redirect to login
        logout();
        break;
      case 403:
        // Forbidden - show access denied message
        showError('You do not have permission to perform this action');
        break;
      case 422:
        // Validation errors
        showValidationErrors(data.errors);
        break;
      case 429:
        // Rate limit
        showError('Rate limit exceeded. Please try again later.');
        break;
      default:
        showError(data.message || 'An error occurred');
    }
  } else {
    // Network error
    showError('Network error. Please check your connection.');
  }
};
```

---

## 📱 State Management Example

### **Using React Context**
```javascript
// contexts/AuthContext.js
const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  
  useEffect(() => {
    loadUser();
  }, []);
  
  const loadUser = async () => {
    try {
      const userData = await getCurrentUser();
      setUser(userData);
    } catch (error) {
      setUser(null);
    } finally {
      setLoading(false);
    }
  };
  
  const login = async (email, password) => {
    const userData = await loginUser(email, password);
    setUser(userData);
    return userData;
  };
  
  const logout = async () => {
    await logoutUser();
    setUser(null);
  };
  
  return (
    <AuthContext.Provider value={{ user, login, logout, loading }}>
      {children}
    </AuthContext.Provider>
  );
};
```

---

## 🎯 Best Practices

1. **Token Management:**
   - Store token in localStorage or secure cookie
   - Refresh token before expiration
   - Clear token on logout

2. **Error Handling:**
   - Always handle API errors gracefully
   - Show user-friendly error messages
   - Log errors for debugging

3. **Loading States:**
   - Show loading indicators during API calls
   - Disable buttons during requests
   - Use optimistic updates where appropriate

4. **Caching:**
   - Cache frequently accessed data
   - Invalidate cache on updates
   - Use React Query or SWR for caching

5. **Real-time Updates:**
   - Subscribe to channels on mount
   - Unsubscribe on unmount
   - Handle connection errors

---

## 📚 API Documentation

**Interactive Swagger UI:** Visit `/api/documentation` for complete API documentation with:
- All endpoints
- Request/response schemas
- Try-it-out functionality
- Authentication testing

---

## 🔗 Quick Reference

**Base URL:** `/api/v1`  
**Auth Header:** `Authorization: Bearer {token}`  
**Response Format:** `{ success, message, data }`  
**Pagination:** `{ data: [...], meta: { current_page, total, ... } }`

---

**Happy Coding!** 🚀

