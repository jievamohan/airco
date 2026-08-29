/**
 * Houdt niet-ingelogde bezoekers buiten het dashboard.
 * Draait alleen client-side; de dashboardroutes zijn immers ssr: false.
 */
export default defineNuxtRouteMiddleware((to) => {
  if (import.meta.server) return
  if (to.path === '/dashboard/login') return

  if (!readToken()) {
    return navigateTo('/dashboard/login')
  }
})
