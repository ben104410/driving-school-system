@echo off
title AA Driving School Management System
echo ========================================================
echo  AA DRIVING SCHOOL MANAGEMENT SYSTEM - MOMBASA BRANCH
echo  Academic Research Project - Cynthia Jeruto (KeMU)
echo ========================================================
echo.
echo Starting system server...
cd /d "%~dp0node"
start "" "http://localhost:3000"
node server.js
pause
