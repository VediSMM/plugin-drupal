# VediSMM for Drupal Guide

VediSMM for Drupal adds a permission-protected content submission workflow for Drupal 11.

The module stores the VediSMM token in State API, not exported configuration. The default action creates a VediSMM draft; publish is an explicit action protected by Drupal permission and CSRF checks.
