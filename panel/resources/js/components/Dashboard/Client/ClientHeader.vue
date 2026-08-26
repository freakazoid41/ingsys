<template>
  <div class="header-section">
    <div class="header-content">
      <div>
        <h1 class="greeting-title">{{ greeting }}, <span class="user-name">{{ userName }}</span></h1>
        <p class="header-subtitle">Tedarikçi Paneli</p>
      </div>
      <div class="header-icons">
        <button class="icon-btn notification-btn" @click="openNotifications">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
          <span v-if="hasNotifications" class="notification-badge"></span>
        </button>
        <button class="icon-btn profile-btn" @click="openProfile">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth';

export default {
  name: 'ClientHeader',
  props: {
    hasNotifications: {
      type: Boolean,
      default: false
    }
  },
  emits: ['open-notifications', 'open-profile'],
  data() {
    const authStore = useAuthStore();
    return {
      userName: authStore.userName,
      greeting: 'Hoş Geldiniz'
    };
  },
  methods: {
    openNotifications() {
      this.$emit('open-notifications');
    },
    openProfile() {
      this.$emit('open-profile');
    }
  }
};
</script>

<style scoped>
.header-section {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  margin-bottom: 2.5rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.greeting-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: #212529;
  margin: 0;
}

.user-name {
  color: #0d6efd;
}

.header-subtitle {
  color: #6c757d;
  font-size: 0.95rem;
  margin: 0.5rem 0 0;
}

.header-icons {
  display: flex;
  gap: 1rem;
}

.icon-btn {
  width: 44px;
  height: 44px;
  border: none;
  background: #f8f9fa;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6c757d;
  transition: all 0.3s ease;
}

.icon-btn:hover {
  background: #0d6efd;
  color: white;
  transform: scale(1.05);
}

.notification-btn {
  position: relative;
}

.notification-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 12px;
  height: 12px;
  background: #dc3545;
  border-radius: 50%;
  animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  box-shadow: 0 0 0 rgba(220, 53, 69, 0.7);
}

@keyframes pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
  }

  50% {
    box-shadow: 0 0 0 6px rgba(220, 53, 69, 0);
  }

  100% {
    box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
  }
}

@media (max-width: 768px) {
  .header-section {
    padding: 1.5rem;
  }

  .header-content {
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
  }

  .header-icons {
    align-self: flex-end;
  }

  .greeting-title {
    font-size: 1.5rem;
  }
}

@media (max-width: 576px) {
  .header-section {
    padding: 1rem;
    margin-bottom: 1.5rem;
  }

  .greeting-title {
    font-size: 1.3rem;
  }
}
</style>
